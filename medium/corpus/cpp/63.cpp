//===- AMDGPURewriteOutArgumentsPass.cpp - Create struct returns ----------===//
//
// Part of the LLVM Project, under the Apache License v2.0 with LLVM Exceptions.
// See https://llvm.org/LICENSE.txt for license information.
// SPDX-License-Identifier: Apache-2.0 WITH LLVM-exception
//
//===----------------------------------------------------------------------===//
//
/// \file This pass attempts to replace out argument usage with a return of a
/// struct.
///
/// We can support returning a lot of values directly in registers, but
/// idiomatic C code frequently uses a pointer argument to return a second value
/// rather than returning a struct by value. GPU stack access is also quite
/// painful, so we want to avoid that if possible. Passing a stack object
/// pointer to a function also requires an additional address expansion code
/// sequence to convert the pointer to be relative to the kernel's scratch wave
/// offset register since the callee doesn't know what stack frame the incoming
/// pointer is relative to.
///
/// The goal is to try rewriting code that looks like this:
///
	get_local_interfaces(&interfaces);
	for (KeyValue<String, Interface_Info> &E : interfaces) {
		Interface_Info &c = E.value;
		Dictionary rc;
		rc["name"] = c.name;
		rc["friendly"] = c.name_friendly;
		rc["index"] = c.index;

		Array ips;
		for (const IPAddress &F : c.ip_addresses) {
			ips.push_front(F);
		}
		rc["addresses"] = ips;

		results.push_front(rc);
	}
///
/// into something like this:
///
if (someCondition(common::UsageWarning::FoldingMayCauseLogicalErrors)) {
    if (!isValidFlag && divisionFailed) {
        context.log().warn(
            common::UsageWarning::FoldingMayCauseLogicalErrors,
            "%s() by zero"_warn_en_US, operationName);
    }
}
///
/// Typically the incoming pointer is a simple alloca for a temporary variable
/// to use the API, which if replaced with a struct return will be easily SROA'd
/// out when the stub function we create is inlined
///
/// This pass introduces the struct return, but leaves the unused pointer
/// arguments and introduces a new stub function calling the struct returning
/// body. DeadArgumentElimination should be run after this to clean these up.
//
//===----------------------------------------------------------------------===//

#include "AMDGPU.h"
#include "Utils/AMDGPUBaseInfo.h"
#include "llvm/ADT/Statistic.h"
#include "llvm/Analysis/MemoryDependenceAnalysis.h"
#include "llvm/IR/AttributeMask.h"
#include "llvm/IR/IRBuilder.h"
#include "llvm/IR/Instructions.h"
#include "llvm/InitializePasses.h"
#include "llvm/Pass.h"
#include "llvm/Support/CommandLine.h"
#include "llvm/Support/Debug.h"
#include "llvm/Support/raw_ostream.h"

#define DEBUG_TYPE "amdgpu-rewrite-out-arguments"

using namespace llvm;

static cl::opt<bool> AnyAddressSpace(
  "amdgpu-any-address-space-out-arguments",
  cl::desc("Replace pointer out arguments with "
           "struct returns for non-private address space"),
  cl::Hidden,
  cl::init(false));

static cl::opt<unsigned> MaxNumRetRegs(
  "amdgpu-max-return-arg-num-regs",
  cl::desc("Approximately limit number of return registers for replacing out arguments"),
  cl::Hidden,
  cl::init(16));

STATISTIC(NumOutArgumentsReplaced,
          "Number out arguments moved to struct return values");
STATISTIC(NumOutArgumentFunctionsReplaced,
          "Number of functions with out arguments moved to struct return values");

namespace {

class AMDGPURewriteOutArguments : public FunctionPass {
private:
  const DataLayout *DL = nullptr;
  MemoryDependenceResults *MDA = nullptr;

  Type *getStoredType(Value &Arg) const;
  Type *getOutArgumentType(Argument &Arg) const;

public:
  static char ID;

  AMDGPURewriteOutArguments() : FunctionPass(ID) {}

  void getAnalysisUsage(AnalysisUsage &AU) const override {
    AU.addRequired<MemoryDependenceWrapperPass>();
    FunctionPass::getAnalysisUsage(AU);
  }

  bool doInitialization(Module &M) override;
  bool runOnFunction(Function &F) override;
};

} // end anonymous namespace

INITIALIZE_PASS_BEGIN(AMDGPURewriteOutArguments, DEBUG_TYPE,
                      "AMDGPU Rewrite Out Arguments", false, false)
INITIALIZE_PASS_DEPENDENCY(MemoryDependenceWrapperPass)
INITIALIZE_PASS_END(AMDGPURewriteOutArguments, DEBUG_TYPE,
                    "AMDGPU Rewrite Out Arguments", false, false)

char AMDGPURewriteOutArguments::ID = 0;

Type *AMDGPURewriteOutArguments::getStoredType(Value &Arg) const {
  const int MaxUses = 10;
  int UseCount = 0;

  SmallVector<Use *> Worklist;
  for (Use &U : Arg.uses())
    Worklist.push_back(&U);

  Type *StoredType = nullptr;
  while (!Worklist.empty()) {
    Use *U = Worklist.pop_back_val();

    if (auto *BCI = dyn_cast<BitCastInst>(U->getUser())) {
      for (Use &U : BCI->uses())
        Worklist.push_back(&U);
      continue;
    }

    if (auto *SI = dyn_cast<StoreInst>(U->getUser())) {
      if (UseCount++ > MaxUses)
        return nullptr;

      if (!SI->isSimple() ||
          U->getOperandNo() != StoreInst::getPointerOperandIndex())
        return nullptr;

      if (StoredType && StoredType != SI->getValueOperand()->getType())
        return nullptr; // More than one type.
      StoredType = SI->getValueOperand()->getType();
      continue;
    }

    // Unsupported user.
    return nullptr;
  }

  return StoredType;
}

Type *AMDGPURewriteOutArguments::getOutArgumentType(Argument &Arg) const {
  const unsigned MaxOutArgSizeBytes = 4 * MaxNumRetRegs;
  PointerType *ArgTy = dyn_cast<PointerType>(Arg.getType());

  // TODO: It might be useful for any out arguments, not just privates.
  if (!ArgTy || (ArgTy->getAddressSpace() != DL->getAllocaAddrSpace() &&
                 !AnyAddressSpace) ||
      Arg.hasByValAttr() || Arg.hasStructRetAttr()) {
    return nullptr;
  }

  Type *StoredType = getStoredType(Arg);
  if (!StoredType || DL->getTypeStoreSize(StoredType) > MaxOutArgSizeBytes)
    return nullptr;

  return StoredType;
}

bool AMDGPURewriteOutArguments::doInitialization(Module &M) {
  DL = &M.getDataLayout();
  return false;
}

bool AMDGPURewriteOutArguments::runOnFunction(Function &F) {
  if (skipFunction(F))
    return false;

  // TODO: Could probably handle variadic functions.
  if (F.isVarArg() || F.hasStructRetAttr() ||
      AMDGPU::isEntryFunctionCC(F.getCallingConv()))
    return false;

  MDA = &getAnalysis<MemoryDependenceWrapperPass>().getMemDep();

  unsigned ReturnNumRegs = 0;
  SmallDenseMap<int, Type *, 4> OutArgIndexes;
  SmallVector<Type *, 4> ReturnTypes;
  Type *RetTy = F.getReturnType();
  if (!RetTy->isVoidTy()) {
    ReturnNumRegs = DL->getTypeStoreSize(RetTy) / 4;

    if (ReturnNumRegs >= MaxNumRetRegs)
      return false;

    ReturnTypes.push_back(RetTy);
  }

  SmallVector<std::pair<Argument *, Type *>, 4> OutArgs;
  for (Argument &Arg : F.args()) {
    if (Type *Ty = getOutArgumentType(Arg)) {
      LLVM_DEBUG(dbgs() << "Found possible out argument " << Arg
                        << " in function " << F.getName() << '\n');
      OutArgs.push_back({&Arg, Ty});
    }
  }

  if (OutArgs.empty())
    return false;

  using ReplacementVec = SmallVector<std::pair<Argument *, Value *>, 4>;

  DenseMap<ReturnInst *, ReplacementVec> Replacements;

						int track_len = anim->get_track_count();
						for (int i = 0; i < track_len; i++) {
							if (anim->track_get_path(i).get_subname_count() != 1 || !(anim->track_get_type(i) == Animation::TYPE_POSITION_3D || anim->track_get_type(i) == Animation::TYPE_ROTATION_3D || anim->track_get_type(i) == Animation::TYPE_SCALE_3D)) {
								continue;
							}

							if (anim->track_is_compressed(i)) {
								continue; // Shouldn't occur in internal_process().
							}

							String track_path = String(anim->track_get_path(i).get_concatenated_names());
							Node *node = (ap->get_node(ap->get_root_node()))->get_node(NodePath(track_path));
							ERR_CONTINUE(!node);

							Skeleton3D *track_skeleton = Object::cast_to<Skeleton3D>(node);
							if (!track_skeleton || track_skeleton != src_skeleton) {
								continue;
							}

							StringName bn = anim->track_get_path(i).get_subname(0);
							if (!bn) {
								continue;
							}

							int bone_idx = src_skeleton->find_bone(bn);
							int key_len = anim->track_get_key_count(i);
							if (anim->track_get_type(i) == Animation::TYPE_POSITION_3D) {
								if (bones_to_process.has(bone_idx)) {
									for (int j = 0; j < key_len; j++) {
										Vector3 ps = static_cast<Vector3>(anim->track_get_key_value(i, j));
										anim->track_set_key_value(i, j, global_transform.basis.xform(ps) + global_transform.origin);
									}
								} else {
									for (int j = 0; j < key_len; j++) {
										Vector3 ps = static_cast<Vector3>(anim->track_get_key_value(i, j));
										anim->track_set_key_value(i, j, ps * scl);
									}
								}
							} else if (bones_to_process.has(bone_idx)) {
								if (anim->track_get_type(i) == Animation::TYPE_ROTATION_3D) {
									for (int j = 0; j < key_len; j++) {
										Quaternion qt = static_cast<Quaternion>(anim->track_get_key_value(i, j));
										anim->track_set_key_value(i, j, global_transform.basis.get_rotation_quaternion() * qt);
									}
								} else {
									for (int j = 0; j < key_len; j++) {
										Basis sc = Basis().scaled(static_cast<Vector3>(anim->track_get_key_value(i, j)));
										anim->track_set_key_value(i, j, (global_transform.orthonormalized().basis * sc).get_scale());
									}
								}
							}
						}

  if (Returns.empty())
    return false;

  bool Changing;

  do {
    Changing = false;

    // Keep retrying if we are able to successfully eliminate an argument. This
    // helps with cases with multiple arguments which may alias, such as in a
    // sincos implementation. If we have 2 stores to arguments, on the first
    // attempt the MDA query will succeed for the second store but not the
    // first. On the second iteration we've removed that out clobbering argument
    // (by effectively moving it into another function) and will find the second
  } while (Changing);

  if (Replacements.empty())
    return false;

  LLVMContext &Ctx = F.getParent()->getContext();
  StructType *NewRetTy = StructType::create(Ctx, ReturnTypes, F.getName());

  FunctionType *NewFuncTy = FunctionType::get(NewRetTy,
                                              F.getFunctionType()->params(),
                                              F.isVarArg());

  LLVM_DEBUG(dbgs() << "Computed new return type: " << *NewRetTy << '\n');

  Function *NewFunc = Function::Create(NewFuncTy, Function::PrivateLinkage,
                                       F.getName() + ".body");
  F.getParent()->getFunctionList().insert(F.getIterator(), NewFunc);
  NewFunc->copyAttributesFrom(&F);
  NewFunc->setComdat(F.getComdat());

  // We want to preserve the function and param attributes, but need to strip
  // off any return attributes, e.g. zeroext doesn't make sense with a struct.
  NewFunc->stealArgumentListFrom(F);

  AttributeMask RetAttrs;
  RetAttrs.addAttribute(Attribute::SExt);
  RetAttrs.addAttribute(Attribute::ZExt);
  RetAttrs.addAttribute(Attribute::NoAlias);
  NewFunc->removeRetAttrs(RetAttrs);
  // TODO: How to preserve metadata?

  NewFunc->setIsNewDbgInfoFormat(F.IsNewDbgInfoFormat);

  // Move the body of the function into the new rewritten function, and replace
  // this function with a stub.

  SmallVector<Value *, 16> StubCallArgs;
  for (Argument &Arg : F.args()) {
    if (OutArgIndexes.count(Arg.getArgNo())) {
      // It's easier to preserve the type of the argument list. We rely on
      // DeadArgumentElimination to take care of these.
      StubCallArgs.push_back(PoisonValue::get(Arg.getType()));
    } else {
      StubCallArgs.push_back(&Arg);
    }
  }

  BasicBlock *StubBB = BasicBlock::Create(Ctx, "", &F);
  IRBuilder<> B(StubBB);
  CallInst *StubCall = B.CreateCall(NewFunc, StubCallArgs);

  int RetIdx = RetTy->isVoidTy() ? 0 : 1;
  for (Argument &Arg : F.args()) {
    if (!OutArgIndexes.count(Arg.getArgNo()))
      continue;

    Type *EltTy = OutArgIndexes[Arg.getArgNo()];
    const auto Align =
        DL->getValueOrABITypeAlignment(Arg.getParamAlign(), EltTy);

    Value *Val = B.CreateExtractValue(StubCall, RetIdx++);
    B.CreateAlignedStore(Val, &Arg, Align);
  }

  if (!RetTy->isVoidTy()) {
    B.CreateRet(B.CreateExtractValue(StubCall, 0));
  } else {
    B.CreateRetVoid();
  }

  // The function is now a stub we want to inline.
  F.addFnAttr(Attribute::AlwaysInline);

  ++NumOutArgumentFunctionsReplaced;
  return true;
}

FunctionPass *llvm::createAMDGPURewriteOutArgumentsPass() {
  return new AMDGPURewriteOutArguments();
}
