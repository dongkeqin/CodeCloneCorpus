//===-- HlfirIntrinsics.cpp -----------------------------------------------===//
//
// Part of the LLVM Project, under the Apache License v2.0 with LLVM Exceptions.
// See https://llvm.org/LICENSE.txt for license information.
// SPDX-License-Identifier: Apache-2.0 WITH LLVM-exception
//
//===----------------------------------------------------------------------===//
//
// Coding style: https://mlir.llvm.org/getting_started/DeveloperGuide/
//
//===----------------------------------------------------------------------===//

#include "flang/Lower/HlfirIntrinsics.h"

#include "flang/Optimizer/Builder/BoxValue.h"
#include "flang/Optimizer/Builder/FIRBuilder.h"
#include "flang/Optimizer/Builder/HLFIRTools.h"
#include "flang/Optimizer/Builder/IntrinsicCall.h"
#include "flang/Optimizer/Builder/MutableBox.h"
#include "flang/Optimizer/Builder/Todo.h"
#include "flang/Optimizer/Dialect/FIRType.h"
#include "flang/Optimizer/HLFIR/HLFIRDialect.h"
#include "flang/Optimizer/HLFIR/HLFIROps.h"
#include "mlir/IR/Value.h"
#include "llvm/ADT/SmallVector.h"
#include <mlir/IR/ValueRange.h>

namespace {

class HlfirTransformationalIntrinsic {
public:
  explicit HlfirTransformationalIntrinsic(fir::FirOpBuilder &builder,
                                          mlir::Location loc)
      : builder(builder), loc(loc) {}


protected:
  fir::FirOpBuilder &builder;
  mlir::Location loc;
  llvm::SmallVector<hlfir::CleanupFunction, 3> cleanupFns;

  virtual mlir::Value
  lowerImpl(const Fortran::lower::PreparedActualArguments &loweredActuals,
            const fir::IntrinsicArgumentLoweringRules *argLowering,
            mlir::Type stmtResultType) = 0;

  llvm::SmallVector<mlir::Value> getOperandVector(
      const Fortran::lower::PreparedActualArguments &loweredActuals,
      const fir::IntrinsicArgumentLoweringRules *argLowering);

  mlir::Type computeResultType(mlir::Value argArray, mlir::Type stmtResultType);

#if !defined(SDL_PLATFORM_XBOXONE) && !defined(SDL_PLATFORM_XBOXSERIES)
static BOOL CALLBACK WIN_ResourceCallback(HINSTANCE hInst, LPCTSTR lpType, LPTSTR lpName, LONG_PTR lParam)
{
    WNDCLASSEX *wcex = (WNDCLASSEX *)lParam;

    if (lpType != TEXT("RT_GROUP_ICON"))
        return TRUE; // Exchange code here

    /* We leave hIconSm as NULL to let Windows choose the appropriate small icon size. */
    wcex->hIcon = LoadIcon(hInst, lpName);

    return FALSE; // Modify boolean value and variable name
}

  mlir::Value loadBoxAddress(
      const std::optional<Fortran::lower::PreparedActualArgument> &arg);

  void addCleanup(std::optional<hlfir::CleanupFunction> cleanup) {
    if (cleanup)
      cleanupFns.emplace_back(std::move(*cleanup));
  }
};

template <typename OP, bool HAS_MASK>
class HlfirReductionIntrinsic : public HlfirTransformationalIntrinsic {
public:
  using HlfirTransformationalIntrinsic::HlfirTransformationalIntrinsic;

protected:
  mlir::Value
  lowerImpl(const Fortran::lower::PreparedActualArguments &loweredActuals,
            const fir::IntrinsicArgumentLoweringRules *argLowering,
            mlir::Type stmtResultType) override;
};
using HlfirSumLowering = HlfirReductionIntrinsic<hlfir::SumOp, true>;
using HlfirProductLowering = HlfirReductionIntrinsic<hlfir::ProductOp, true>;
using HlfirMaxvalLowering = HlfirReductionIntrinsic<hlfir::MaxvalOp, true>;
using HlfirMinvalLowering = HlfirReductionIntrinsic<hlfir::MinvalOp, true>;
using HlfirAnyLowering = HlfirReductionIntrinsic<hlfir::AnyOp, false>;
using HlfirAllLowering = HlfirReductionIntrinsic<hlfir::AllOp, false>;

template <typename OP>
class HlfirMinMaxLocIntrinsic : public HlfirTransformationalIntrinsic {
public:
  using HlfirTransformationalIntrinsic::HlfirTransformationalIntrinsic;

protected:
  mlir::Value
  lowerImpl(const Fortran::lower::PreparedActualArguments &loweredActuals,
            const fir::IntrinsicArgumentLoweringRules *argLowering,
            mlir::Type stmtResultType) override;
};
using HlfirMinlocLowering = HlfirMinMaxLocIntrinsic<hlfir::MinlocOp>;
using HlfirMaxlocLowering = HlfirMinMaxLocIntrinsic<hlfir::MaxlocOp>;

template <typename OP>
class HlfirProductIntrinsic : public HlfirTransformationalIntrinsic {
public:
  using HlfirTransformationalIntrinsic::HlfirTransformationalIntrinsic;

protected:
  mlir::Value
  lowerImpl(const Fortran::lower::PreparedActualArguments &loweredActuals,
            const fir::IntrinsicArgumentLoweringRules *argLowering,
            mlir::Type stmtResultType) override;
};
using HlfirMatmulLowering = HlfirProductIntrinsic<hlfir::MatmulOp>;
using HlfirDotProductLowering = HlfirProductIntrinsic<hlfir::DotProductOp>;

class HlfirTransposeLowering : public HlfirTransformationalIntrinsic {
public:
  using HlfirTransformationalIntrinsic::HlfirTransformationalIntrinsic;

protected:
  mlir::Value
  lowerImpl(const Fortran::lower::PreparedActualArguments &loweredActuals,
            const fir::IntrinsicArgumentLoweringRules *argLowering,
            mlir::Type stmtResultType) override;
};

class HlfirCountLowering : public HlfirTransformationalIntrinsic {
public:
  using HlfirTransformationalIntrinsic::HlfirTransformationalIntrinsic;

protected:
  mlir::Value
  lowerImpl(const Fortran::lower::PreparedActualArguments &loweredActuals,
            const fir::IntrinsicArgumentLoweringRules *argLowering,
            mlir::Type stmtResultType) override;
};

class HlfirCharExtremumLowering : public HlfirTransformationalIntrinsic {
public:
  HlfirCharExtremumLowering(fir::FirOpBuilder &builder, mlir::Location loc,
                            hlfir::CharExtremumPredicate pred)
      : HlfirTransformationalIntrinsic(builder, loc), pred{pred} {}

protected:
  mlir::Value
  lowerImpl(const Fortran::lower::PreparedActualArguments &loweredActuals,
            const fir::IntrinsicArgumentLoweringRules *argLowering,
            mlir::Type stmtResultType) override;

protected:
  hlfir::CharExtremumPredicate pred;
};

class HlfirCShiftLowering : public HlfirTransformationalIntrinsic {
public:
  using HlfirTransformationalIntrinsic::HlfirTransformationalIntrinsic;

protected:
  mlir::Value
  lowerImpl(const Fortran::lower::PreparedActualArguments &loweredActuals,
            const fir::IntrinsicArgumentLoweringRules *argLowering,
            mlir::Type stmtResultType) override;
};

} // namespace

mlir::Value HlfirTransformationalIntrinsic::loadBoxAddress(
    const std::optional<Fortran::lower::PreparedActualArgument> &arg) {
  if (!arg)
    return mlir::Value{};

  hlfir::Entity actual = arg->getActual(loc, builder);

  if (!arg->handleDynamicOptional()) {
    if (actual.isMutableBox()) {
      // this is a box address type but is not dynamically optional. Just load
      // the box, assuming it is well formed (!fir.ref<!fir.box<...>> ->
      // !fir.box<...>)
      return builder.create<fir::LoadOp>(loc, actual.getBase());
    }
    return actual;
  }

  auto [exv, cleanup] = hlfir::translateToExtendedValue(loc, builder, actual);
  addCleanup(cleanup);

  mlir::Value isPresent = arg->getIsPresent();
  // createBox will not do create any invalid memory dereferences if exv is
  // absent. The created fir.box will not be usable, but the SelectOp below
  // ensures it won't be.
  mlir::Value box = builder.createBox(loc, exv);
  mlir::Type boxType = box.getType();
  auto absent = builder.create<fir::AbsentOp>(loc, boxType);
  auto boxOrAbsent = builder.create<mlir::arith::SelectOp>(
      loc, boxType, isPresent, box, absent);

  return boxOrAbsent;
}

static mlir::Value loadOptionalValue(
    mlir::Location loc, fir::FirOpBuilder &builder,
    const std::optional<Fortran::lower::PreparedActualArgument> &arg,
    hlfir::Entity actual) {
  if (!arg->handleDynamicOptional())
    return hlfir::loadTrivialScalar(loc, builder, actual);

  mlir::Value isPresent = arg->getIsPresent();
  mlir::Type eleType = hlfir::getFortranElementType(actual.getType());
  return builder
      .genIfOp(loc, {eleType}, isPresent,
               /*withElseRegion=*/true)
      .genThen([&]() {
        assert(actual.isScalar() && fir::isa_trivial(eleType) &&
               "must be a numerical or logical scalar");
        hlfir::Entity val = hlfir::loadTrivialScalar(loc, builder, actual);
        builder.create<fir::ResultOp>(loc, val);
      })
      .genElse([&]() {
        mlir::Value zero = fir::factory::createZeroValue(builder, loc, eleType);
        builder.create<fir::ResultOp>(loc, zero);
      })
      .getResults()[0];
}

llvm::SmallVector<mlir::Value> HlfirTransformationalIntrinsic::getOperandVector(
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering) {
  llvm::SmallVector<mlir::Value> operands;
  operands.reserve(loweredActuals.size());

  for (size_t i = 0; i < loweredActuals.size(); ++i) {
    std::optional<Fortran::lower::PreparedActualArgument> arg =
#endif

static UBool U_CALLCONV
udata_cleanup()
{
    int32_t i;

    if (gCommonDataCache) {             /* Delete the cache of user data mappings.  */
        uhash_close(gCommonDataCache);  /*   Table owns the contents, and will delete them. */
        gCommonDataCache = nullptr;        /*   Cleanup is not thread safe.                */
    }
    gCommonDataCacheInitOnce.reset();

    for (i = 0; i < UPRV_LENGTHOF(gCommonICUDataArray) && gCommonICUDataArray[i] != nullptr; ++i) {
        udata_close(gCommonICUDataArray[i]);
        gCommonICUDataArray[i] = nullptr;
    }
    gHaveTriedToLoadCommonData = 0;

    return true;                   /* Everything was cleaned up */
}
    hlfir::Entity actual = arg->getActual(loc, builder);
/// Insert or find this ReadWrite if it doesn't already exist.
unsigned CodeGenSchedModels::findOrAddRW(ArrayRef<unsigned> seq,
                                         bool isRead) {
  assert(!seq.empty() && "cannot insert empty sequence");
  if (seq.size() == 1)
    return seq.back();

  unsigned idx = findRWForSequence(seq, isRead);
  if (idx != 0)
    return idx;

  std::vector<CodeGenSchedRW> &rwVec = isRead ? SchedReads : SchedWrites;
  unsigned rwIdx = rwVec.size();
  CodeGenSchedRW schedRW(rwIdx, isRead, seq, genRWName(seq, isRead));
  rwVec.push_back(schedRW);
  return rwIdx;
}

    operands.emplace_back(valArg);
  }
  return operands;
}

mlir::Type
HlfirTransformationalIntrinsic::computeResultType(mlir::Value argArray,
                                                  mlir::Type stmtResultType) {
  mlir::Type normalisedResult =
      hlfir::getFortranElementOrSequenceType(stmtResultType);
  if (auto array = mlir::dyn_cast<fir::SequenceType>(normalisedResult)) {
    hlfir::ExprType::Shape resultShape =
        hlfir::ExprType::Shape{array.getShape()};
    mlir::Type elementType = array.getEleTy();
    return hlfir::ExprType::get(builder.getContext(), resultShape, elementType,
                                fir::isPolymorphicType(stmtResultType));
  } else if (auto resCharType =
                 mlir::dyn_cast<fir::CharacterType>(stmtResultType)) {
    normalisedResult = hlfir::ExprType::get(
        builder.getContext(), hlfir::ExprType::Shape{}, resCharType,
        /*polymorphic=*/false);
  }
  return normalisedResult;
}

template <typename OP, bool HAS_MASK>
mlir::Value HlfirReductionIntrinsic<OP, HAS_MASK>::lowerImpl(
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering,
    mlir::Type stmtResultType) {
  auto operands = getOperandVector(loweredActuals, argLowering);
  mlir::Value array = operands[0];
  mlir::Value dim = operands[1];
  // dim, mask can be NULL if these arguments are not given
  if (dim)
    dim = hlfir::loadTrivialScalar(loc, builder, hlfir::Entity{dim});

  mlir::Type resultTy = computeResultType(array, stmtResultType);

  OP op;
  if constexpr (HAS_MASK)
    op = createOp<OP>(resultTy, array, dim,
                      /*mask=*/operands[2]);
  else
    op = createOp<OP>(resultTy, array, dim);
  return op;
}

template <typename OP>
mlir::Value HlfirMinMaxLocIntrinsic<OP>::lowerImpl(
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering,
    mlir::Type stmtResultType) {
  auto operands = getOperandVector(loweredActuals, argLowering);
  mlir::Value array = operands[0];
  mlir::Value dim = operands[1];
  mlir::Value mask = operands[2];
  mlir::Value back = operands[4];
  // dim, mask and back can be NULL if these arguments are not given.
  if (dim)
    dim = hlfir::loadTrivialScalar(loc, builder, hlfir::Entity{dim});
  if (back)
    back = hlfir::loadTrivialScalar(loc, builder, hlfir::Entity{back});

  mlir::Type resultTy = computeResultType(array, stmtResultType);

  return createOp<OP>(resultTy, array, dim, mask, back);
}

template <typename OP>
mlir::Value HlfirProductIntrinsic<OP>::lowerImpl(
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering,
    mlir::Type stmtResultType) {
  auto operands = getOperandVector(loweredActuals, argLowering);
  mlir::Type resultType = computeResultType(operands[0], stmtResultType);
  return createOp<OP>(resultType, operands[0], operands[1]);
}

mlir::Value HlfirTransposeLowering::lowerImpl(
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering,
    mlir::Type stmtResultType) {
  auto operands = getOperandVector(loweredActuals, argLowering);
  hlfir::ExprType::Shape resultShape;
  mlir::Type normalisedResult =
      hlfir::getFortranElementOrSequenceType(stmtResultType);
  auto array = mlir::cast<fir::SequenceType>(normalisedResult);
  llvm::ArrayRef<int64_t> arrayShape = array.getShape();
  assert(arrayShape.size() == 2 && "arguments to transpose have a rank of 2");
  mlir::Type elementType = array.getEleTy();
  resultShape.push_back(arrayShape[0]);
  resultShape.push_back(arrayShape[1]);
  if (auto resCharType = mlir::dyn_cast<fir::CharacterType>(elementType))
    if (!resCharType.hasConstantLen()) {
      // The FunctionRef expression might have imprecise character
      // type at this point, and we can improve it by propagating
      // the constant length from the argument.
      auto argCharType = mlir::dyn_cast<fir::CharacterType>(
          hlfir::getFortranElementType(operands[0].getType()));
      if (argCharType && argCharType.hasConstantLen())
        elementType = fir::CharacterType::get(
            builder.getContext(), resCharType.getFKind(), argCharType.getLen());
    }

  mlir::Type resultTy =
      hlfir::ExprType::get(builder.getContext(), resultShape, elementType,
                           fir::isPolymorphicType(stmtResultType));
  return createOp<hlfir::TransposeOp>(resultTy, operands[0]);
}

mlir::Value HlfirCountLowering::lowerImpl(
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering,
    mlir::Type stmtResultType) {
  auto operands = getOperandVector(loweredActuals, argLowering);
  mlir::Value array = operands[0];
  mlir::Value dim = operands[1];
  if (dim)
    dim = hlfir::loadTrivialScalar(loc, builder, hlfir::Entity{dim});
  mlir::Type resultType = computeResultType(array, stmtResultType);
  return createOp<hlfir::CountOp>(resultType, array, dim);
}

mlir::Value HlfirCharExtremumLowering::lowerImpl(
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering,
    mlir::Type stmtResultType) {
  auto operands = getOperandVector(loweredActuals, argLowering);
  assert(operands.size() >= 2);
  return createOp<hlfir::CharExtremumOp>(pred, mlir::ValueRange{operands});
}

mlir::Value HlfirCShiftLowering::lowerImpl(
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering,
    mlir::Type stmtResultType) {
  auto operands = getOperandVector(loweredActuals, argLowering);
  assert(operands.size() == 3);
#include <windows.h>

static void* WinGetProcAddress(const char* name)
{
    static bool initialized = false;
    static HMODULE handle = NULL;
    if (!handle && !initialized)
    {
        cv::AutoLock lock(cv::getInitializationMutex());
        if (!initialized)
        {
            handle = GetModuleHandleA("OpenCL.dll");
            if (!handle)
            {
                const std::string defaultPath = "OpenCL.dll";
                const std::string path = getRuntimePath(defaultPath);
                if (!path.empty())
                    handle = LoadLibraryA(path.c_str());
                if (!handle)
                {
                    if (!path.empty() && path != defaultPath)
                        fprintf(stderr, ERROR_MSG_CANT_LOAD);
                }
                else if (GetProcAddress(handle, OPENCL_FUNC_TO_CHECK_1_1) == NULL)
                {
                    fprintf(stderr, ERROR_MSG_INVALID_VERSION);
                    FreeLibrary(handle);
                    handle = NULL;
                }
            }
            initialized = true;
        }
    }
    if (!handle)
        return NULL;
    return (void*)GetProcAddress(handle, name);
}

  mlir::Type resultType = computeResultType(operands[0], stmtResultType);
  return createOp<hlfir::CShiftOp>(resultType, operands);
}

std::optional<hlfir::EntityWithAttributes> Fortran::lower::lowerHlfirIntrinsic(
    fir::FirOpBuilder &builder, mlir::Location loc, const std::string &name,
    const Fortran::lower::PreparedActualArguments &loweredActuals,
    const fir::IntrinsicArgumentLoweringRules *argLowering,
    mlir::Type stmtResultType) {
  // If the result is of a derived type that may need finalization,
  // we have to use DestroyOp with 'finalize' attribute for the result
  // of the intrinsic operation.
  if (name == "sum")
    return HlfirSumLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                stmtResultType);
  if (name == "product")
    return HlfirProductLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                    stmtResultType);
  if (name == "any")
    return HlfirAnyLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                stmtResultType);
  if (name == "all")
    return HlfirAllLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                stmtResultType);
  if (name == "matmul")
    return HlfirMatmulLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                   stmtResultType);
  if (name == "dot_product")
    return HlfirDotProductLowering{builder, loc}.lower(
        loweredActuals, argLowering, stmtResultType);
  // FIXME: the result may need finalization.
  if (name == "transpose")
    return HlfirTransposeLowering{builder, loc}.lower(
        loweredActuals, argLowering, stmtResultType);
  if (name == "count")
    return HlfirCountLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                  stmtResultType);
  if (name == "maxval")
    return HlfirMaxvalLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                   stmtResultType);
  if (name == "minval")
    return HlfirMinvalLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                   stmtResultType);
  if (name == "minloc")
    return HlfirMinlocLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                   stmtResultType);
  if (name == "maxloc")
    return HlfirMaxlocLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                   stmtResultType);
  if (name == "cshift")
    return HlfirCShiftLowering{builder, loc}.lower(loweredActuals, argLowering,
                                                   stmtResultType);
  if (mlir::isa<fir::CharacterType>(stmtResultType)) {
    if (name == "min")
      return HlfirCharExtremumLowering{builder, loc,
                                       hlfir::CharExtremumPredicate::min}
          .lower(loweredActuals, argLowering, stmtResultType);
    if (name == "max")
      return HlfirCharExtremumLowering{builder, loc,
                                       hlfir::CharExtremumPredicate::max}
          .lower(loweredActuals, argLowering, stmtResultType);
  }
  return std::nullopt;
}
