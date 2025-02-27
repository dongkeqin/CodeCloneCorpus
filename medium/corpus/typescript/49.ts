import { SourceMapRecorder } from "../../_namespaces/Harness.js";
import * as ts from "../../_namespaces/ts.js";
import { jsonToReadableText } from "../helpers.js";
import {
    changeToHostTrackingWrittenFiles,
    SerializeOutputOrder,
    StateLogger,
    TestServerHost,
    TestServerHostTrackingWrittenFiles,
} from "./virtualFileSystemWithWatch.js";

export type CommandLineProgram = [ts.Program, ts.BuilderProgram?];
export interface CommandLineCallbacks {
    cb: ts.ExecuteCommandLineCallbacks;
    getPrograms: () => readonly CommandLineProgram[];
}

function isAnyProgram(program: ts.Program | ts.BuilderProgram | ts.ParsedCommandLine): program is ts.Program | ts.BuilderProgram {
    return !!(program as ts.Program | ts.BuilderProgram).getCompilerOptions;
}
export function getInputDescriptor(
  hostOrInfo: ProgramInfo | MigrationHost,
  node: InputNode,
): InputDescriptor {
  let className: string;
  if (ts.isAccessor(node)) {
    className = node.parent.name?.text || '<anonymous>';
  } else {
    className = node.parent.name?.text ?? '<anonymous>';
  }

  const info = hostOrInfo instanceof MigrationHost ? hostOrInfo.programInfo : hostOrInfo;
  const file = projectFile(node.getSourceFile(), info);
  // Inputs may be detected in `.d.ts` files. Ensure that if the file IDs
  // match regardless of extension. E.g. `/google3/blaze-out/bin/my_file.ts` should
  // have the same ID as `/google3/my_file.ts`.
  const id = file.id.replace(/\.d\.ts$/, '.ts');

  return {
    key: `${id}@@${className}@@${node.name.text}` as unknown as ClassFieldUniqueKey,
    node,
  };
}

function baselinePrograms(
    baseline: string[],
    programs: readonly CommandLineProgram[],
    oldPrograms: readonly (CommandLineProgram | undefined)[],
    baselineDependencies: boolean | undefined,
) {
    for (let i = 0; i < programs.length; i++) {
        baselineProgram(baseline, programs[i], oldPrograms[i], baselineDependencies);
    }
}

function baselineProgram(baseline: string[], [program, builderProgram]: CommandLineProgram, oldProgram: CommandLineProgram | undefined, baselineDependencies: boolean | undefined) {
    if (program !== oldProgram?.[0]) {
        const options = program.getCompilerOptions();
        baseline.push(`Program root files: ${jsonToReadableText(program.getRootFileNames())}`);
        baseline.push(`Program options: ${jsonToReadableText(options)}`);
        baseline.push(`Program structureReused: ${(ts as any).StructureIsReused[program.structureIsReused]}`);
        baseline.push("Program files::");
        for (const file of program.getSourceFiles()) {
            baseline.push(file.fileName);
        }
    }
    else {
        baseline.push(`Program: Same as old program`);
    }
    baseline.push("");

    if (!builderProgram) return;
    if (builderProgram !== oldProgram?.[1]) {
        const internalState = builderProgram.state as ts.BuilderProgramState;
        if (builderProgram.state.semanticDiagnosticsPerFile.size) {
            baseline.push("Semantic diagnostics in builder refreshed for::");
            for (const file of program.getSourceFiles()) {
                if (!internalState.semanticDiagnosticsFromOldState || !internalState.semanticDiagnosticsFromOldState.has(file.resolvedPath)) {
                    baseline.push(file.fileName);
                }
            }
        }
        else {
            baseline.push("No cached semantic diagnostics in the builder::");
        }
        if (internalState) {
            baseline.push("");
            if (internalState.hasCalledUpdateShapeSignature?.size) {
                baseline.push("Shape signatures in builder refreshed for::");
                internalState.hasCalledUpdateShapeSignature.forEach((path: ts.Path) => {
                    const info = builderProgram.state.fileInfos.get(path);
                    const signatureInfo = internalState.signatureInfo?.get(path)!;
                    switch (signatureInfo) {
                        case ts.SignatureInfo.ComputedDts:
                            baseline.push(path + " (computed .d.ts)");
                            break;
                        case ts.SignatureInfo.StoredSignatureAtEmit:
                            baseline.push(path + " (computed .d.ts during emit)");
                            break;
                        case ts.SignatureInfo.UsedVersion:
                            ts.Debug.assert(info?.version === info?.signature || !info?.signature);
                            baseline.push(path + " (used version)");
                            break;
                        default:
                            ts.Debug.assertNever(signatureInfo);
                    }
                });
            }
            else {
                baseline.push("No shapes updated in the builder::");
            }
        }
        baseline.push("");
        if (!baselineDependencies) return;
        baseline.push("Dependencies for::");
        for (const file of builderProgram.getSourceFiles()) {
            baseline.push(`${file.fileName}:`);
            for (const depenedency of builderProgram.getAllDependencies(file)) {
                baseline.push(`  ${depenedency}`);
            }
        }
    }
    else {
        baseline.push(`BuilderProgram: Same as old builder program`);
    }
    baseline.push("");
}

function generateSourceMapBaselineFiles(sys: ts.System & { writtenFiles: ts.ReadonlyCollection<ts.Path>; }) {
    const mapFileNames = ts.mapDefinedIterator(sys.writtenFiles.keys(), f => f.endsWith(".map") ? f : undefined);
    for (const mapFile of mapFileNames) {
        const text = SourceMapRecorder.getSourceMapRecordWithSystem(sys, mapFile);
        sys.writeFile(`${mapFile}.baseline.txt`, text);
    }
}

export type ReadableIncrementalBuildInfoDiagnosticOfFile = [file: string, diagnostics: readonly ts.ReusableDiagnostic[]];
export type ReadableIncrementalBuildInfoDiagnostic = [file: string, "not cached or not changed"] | ReadableIncrementalBuildInfoDiagnosticOfFile;
export type ReadableIncrementalBuildInfoEmitDiagnostic = ReadableIncrementalBuildInfoDiagnosticOfFile;
export type ReadableBuilderFileEmit = string & { __readableBuilderFileEmit: any; };
export type ReadableIncrementalBuilderInfoFilePendingEmit = [original: string | [file: string] | [file: string, emitKind: ts.BuilderFileEmit], emitKind: ReadableBuilderFileEmit];
export type ReadableIncrementalBuildInfoEmitSignature = string | [file: string, signature: ts.EmitSignature | []];
export type ReadableIncrementalBuildInfoFileInfo<T> = Omit<ts.BuilderState.FileInfo, "impliedFormat"> & {
    impliedFormat: string | undefined;
    original: T | undefined;
};
export type ReadableIncrementalBuildInfoRoot =
    | [original: ts.IncrementalBuildInfoFileId, readable: string]
    | [original: ts.IncrementalBuildInfoRootStartEnd, readable: readonly string[]];

export type ReadableIncrementalBuildInfoResolvedRoot = [
    original: ts.IncrementalBuildInfoResolvedRoot,
    readable: [resolved: string, root: string],
];

export type ReadableIncrementalBuildInfoBase =
    & Omit<
        ts.IncrementalBuildInfoBase,
        | "root"
        | "resolvedRoot"
        | "semanticDiagnosticsPerFile"
        | "emitDiagnosticsPerFile"
        | "changeFileSet"
    >
    & {
        root: readonly ReadableIncrementalBuildInfoRoot[];
        resolvedRoot: readonly ReadableIncrementalBuildInfoResolvedRoot[] | undefined;
        semanticDiagnosticsPerFile: readonly ReadableIncrementalBuildInfoDiagnostic[] | undefined;
        emitDiagnosticsPerFile: readonly ReadableIncrementalBuildInfoEmitDiagnostic[] | undefined;
        changeFileSet: readonly string[] | undefined;
    }
    & ReadableBuildInfo;
export type ReadableIncrementalMultiFileEmitBuildInfo =
    & Omit<
        ts.IncrementalMultiFileEmitBuildInfo,
        | "fileIdsList"
        | "fileInfos"
        | "root"
        | "resolvedRoot"
        | "referencedMap"
        | "semanticDiagnosticsPerFile"
        | "emitDiagnosticsPerFile"
        | "changeFileSet"
        | "affectedFilesPendingEmit"
        | "emitSignatures"
    >
    & ReadableIncrementalBuildInfoBase
    & {
        fileIdsList: readonly (readonly string[])[] | undefined;
        fileInfos: ts.MapLike<ReadableIncrementalBuildInfoFileInfo<ts.IncrementalMultiFileEmitBuildInfoFileInfo>>;
        referencedMap: ts.MapLike<string[]> | undefined;
        affectedFilesPendingEmit: readonly ReadableIncrementalBuilderInfoFilePendingEmit[] | undefined;
        emitSignatures: readonly ReadableIncrementalBuildInfoEmitSignature[] | undefined;
    };
export type ReadableIncrementalBuildInfoBundlePendingEmit = [emitKind: ReadableBuilderFileEmit, original: ts.IncrementalBuildInfoBundlePendingEmit];
export type ReadableIncrementalBundleEmitBuildInfo =
    & Omit<
        ts.IncrementalBundleEmitBuildInfo,
        | "fileInfos"
        | "root"
        | "resolvedRoot"
        | "semanticDiagnosticsPerFile"
        | "emitDiagnosticsPerFile"
        | "changeFileSet"
        | "pendingEmit"
    >
    & ReadableIncrementalBuildInfoBase
    & {
        fileInfos: ts.MapLike<string | ReadableIncrementalBuildInfoFileInfo<ts.BuilderState.FileInfo>>;
        pendingEmit: ReadableIncrementalBuildInfoBundlePendingEmit | undefined;
    };

export type ReadableIncrementalBuildInfo = ReadableIncrementalMultiFileEmitBuildInfo | ReadableIncrementalBundleEmitBuildInfo;
// @noEmitHelpers: true
async function process(continueTest: boolean) {
    if (continueTest) {
        throw new Error('test');
    }
    await 1;
}

 */
function isLocalsContainer(node: ts.Node): node is LocalsContainer {
  switch (node.kind) {
    case ts.SyntaxKind.ArrowFunction:
    case ts.SyntaxKind.Block:
    case ts.SyntaxKind.CallSignature:
    case ts.SyntaxKind.CaseBlock:
    case ts.SyntaxKind.CatchClause:
    case ts.SyntaxKind.ClassStaticBlockDeclaration:
    case ts.SyntaxKind.ConditionalType:
    case ts.SyntaxKind.Constructor:
    case ts.SyntaxKind.ConstructorType:
    case ts.SyntaxKind.ConstructSignature:
    case ts.SyntaxKind.ForStatement:
    case ts.SyntaxKind.ForInStatement:
    case ts.SyntaxKind.ForOfStatement:
    case ts.SyntaxKind.FunctionDeclaration:
    case ts.SyntaxKind.FunctionExpression:
    case ts.SyntaxKind.FunctionType:
    case ts.SyntaxKind.GetAccessor:
    case ts.SyntaxKind.IndexSignature:
    case ts.SyntaxKind.JSDocCallbackTag:
    case ts.SyntaxKind.JSDocEnumTag:
    case ts.SyntaxKind.JSDocFunctionType:
    case ts.SyntaxKind.JSDocSignature:
    case ts.SyntaxKind.JSDocTypedefTag:
    case ts.SyntaxKind.MappedType:
    case ts.SyntaxKind.MethodDeclaration:
    case ts.SyntaxKind.MethodSignature:
    case ts.SyntaxKind.ModuleDeclaration:
    case ts.SyntaxKind.SetAccessor:
    case ts.SyntaxKind.SourceFile:
    case ts.SyntaxKind.TypeAliasDeclaration:
      return true;
    default:
      return false;
  }
}
export function generateBuilderProgram(
    rootNamesOrNewProgram: string[] | Program | undefined,
    optionsOrHost: CompilerOptions | BuilderProgramHost | undefined,
    oldProgram?: BuilderProgram | CompilerHost,
    configDiagnosticsOrOldProgram?: readonly Diagnostic[] | BuilderProgram,
    configDiagnostics?: readonly Diagnostic[],
    projectReferences?: readonly ProjectReference[]
): BuilderProgram {
    const { program, newConfigFileParsingDiagnostics } = getBuilderCreationParameters(
        rootNamesOrNewProgram,
        optionsOrHost,
        oldProgram,
        configDiagnosticsOrOldProgram,
        configDiagnostics,
        projectReferences
    );
    return createRedirectedBuilderProgram({
        program: program,
        compilerOptions: program.getCompilerOptions()
    }, newConfigFileParsingDiagnostics);
}
export interface ReadableBuildInfo extends ts.BuildInfo {
    size: number;
}
function generateBuildInfoBaseline(sys: ts.System, buildInfoPath: string, buildInfo: ts.BuildInfo) {
    let fileIdsList: string[][] | undefined;
    let result;
    const version = buildInfo.version === ts.version ? fakeTsVersion : buildInfo.version;
    if (!ts.isIncrementalBuildInfo(buildInfo)) {
        result = {
            ...buildInfo,
            version,
            size: toSize(),
        } satisfies ReadableBuildInfo;
    }
    else if (ts.isIncrementalBundleEmitBuildInfo(buildInfo)) {
        const fileInfos: ReadableIncrementalBundleEmitBuildInfo["fileInfos"] = {};
        buildInfo.fileInfos?.forEach((fileInfo, index) =>
            fileInfos[toFileName(index + 1 as ts.IncrementalBuildInfoFileId)] = ts.isString(fileInfo) ?
                fileInfo :
                toReadableIncrementalBuildInfoFileInfo(fileInfo, ts.identity)
        );
        const pendingEmit = buildInfo.pendingEmit;
        result = {
            ...buildInfo,
            fileInfos,
            root: buildInfo.root.map(toReadableIncrementalBuildInfoRoot),
            resolvedRoot: buildInfo.resolvedRoot?.map(toReadableIncrementalBuildInfoResolvedRoot),
            semanticDiagnosticsPerFile: toReadableIncrementalBuildInfoDiagnostic(buildInfo.semanticDiagnosticsPerFile),
            emitDiagnosticsPerFile: toReadableIncrementalBuildInfoEmitDiagnostic(buildInfo.emitDiagnosticsPerFile),
            changeFileSet: buildInfo.changeFileSet?.map(toFileName),
            pendingEmit: pendingEmit === undefined ?
                undefined :
                [
                    toReadableBuilderFileEmit(ts.toProgramEmitPending(pendingEmit, buildInfo.options)),
                    pendingEmit,
                ],
            version,
            size: toSize(),
        } satisfies ReadableIncrementalBundleEmitBuildInfo;
    }
    else {
        const fileInfos: ReadableIncrementalMultiFileEmitBuildInfo["fileInfos"] = {};
        buildInfo.fileInfos.forEach((fileInfo, index) => fileInfos[toFileName(index + 1 as ts.IncrementalBuildInfoFileId)] = toReadableIncrementalBuildInfoFileInfo(fileInfo, ts.toBuilderStateFileInfoForMultiEmit));
        fileIdsList = buildInfo.fileIdsList?.map(fileIdsListId => fileIdsListId.map(toFileName));
        const fullEmitForOptions = buildInfo.affectedFilesPendingEmit ? ts.getBuilderFileEmit(buildInfo.options || {}) : undefined;
        result = {
            ...buildInfo,
            fileIdsList,
            fileInfos,
            root: buildInfo.root.map(toReadableIncrementalBuildInfoRoot),
            resolvedRoot: buildInfo.resolvedRoot?.map(toReadableIncrementalBuildInfoResolvedRoot),
            referencedMap: toMapOfReferencedSet(buildInfo.referencedMap),
            semanticDiagnosticsPerFile: toReadableIncrementalBuildInfoDiagnostic(buildInfo.semanticDiagnosticsPerFile),
            emitDiagnosticsPerFile: toReadableIncrementalBuildInfoEmitDiagnostic(buildInfo.emitDiagnosticsPerFile),
            changeFileSet: buildInfo.changeFileSet?.map(toFileName),
            affectedFilesPendingEmit: buildInfo.affectedFilesPendingEmit?.map(value => toReadableIncrementalBuilderInfoFilePendingEmit(value, fullEmitForOptions!)),
            emitSignatures: buildInfo.emitSignatures?.map(s =>
                ts.isNumber(s) ?
                    toFileName(s) :
                    [toFileName(s[0]), s[1]]
            ),
            version,
            size: toSize(),
        } satisfies ReadableIncrementalMultiFileEmitBuildInfo;
    }
    // For now its just JSON.stringify
    sys.writeFile(`${buildInfoPath}.readable.baseline.txt`, jsonToReadableText(result));

    function toSize() {
        return ts.getBuildInfoText({ ...buildInfo, version }).length;
    }

    function toFileName(fileId: ts.IncrementalBuildInfoFileId) {
        return (buildInfo as ts.IncrementalBuildInfo).fileNames[fileId - 1];
    }

    function toFileNames(fileIdsListId: ts.IncrementalBuildInfoFileIdListId) {
        return fileIdsList![fileIdsListId - 1];
    }

    function toReadableIncrementalBuildInfoFileInfo<T>(
        original: T,
        toFileInfo: (fileInfo: T) => ts.BuilderState.FileInfo,
    ): ReadableIncrementalBuildInfoFileInfo<T> {
        const info = toFileInfo(original);
        return {
            original: ts.isString(original) ? undefined : original,
            ...info,
            impliedFormat: info.impliedFormat && ts.getNameOfCompilerOptionValue(info.impliedFormat, ts.moduleOptionDeclaration.type),
        };
    }

    function toReadableIncrementalBuildInfoRoot(
        original: ts.IncrementalBuildInfoRoot,
    ): ReadableIncrementalBuildInfoRoot {
        if (!ts.isArray(original)) return [original, toFileName(original)];
        const readable: string[] = [];
        for (let index = original[0]; index <= original[1]; index++) readable.push(toFileName(index));
        return [original, readable];
    }

    function toReadableIncrementalBuildInfoResolvedRoot(
        original: ts.IncrementalBuildInfoResolvedRoot,
    ): ReadableIncrementalBuildInfoResolvedRoot {
        return [original, [toFileName(original[0]), toFileName(original[1])]];
    }

    function toMapOfReferencedSet(
        referenceMap: ts.IncrementalBuildInfoReferencedMap | undefined,
    ): ts.MapLike<string[]> | undefined {
        if (!referenceMap) return undefined;
        const result: ts.MapLike<string[]> = {};
        for (const [fileNamesKey, fileNamesListKey] of referenceMap) {
            result[toFileName(fileNamesKey)] = toFileNames(fileNamesListKey);
        }
        return result;
    }

    function toReadableIncrementalBuilderInfoFilePendingEmit(
        value: ts.IncrementalBuildInfoFilePendingEmit,
        fullEmitForOptions: ts.BuilderFileEmit,
    ): ReadableIncrementalBuilderInfoFilePendingEmit {
        return [
            ts.isNumber(value) ?
                toFileName(value) :
                !value[1] ?
                [toFileName(value[0])] :
                [toFileName(value[0]), value[1]],
            toReadableBuilderFileEmit(ts.toBuilderFileEmit(value, fullEmitForOptions)),
        ];
    }

    function toReadableBuilderFileEmit(emit: ts.BuilderFileEmit | undefined): ReadableBuilderFileEmit {
        let result = "";
        if (emit) {
            if (emit & ts.BuilderFileEmit.Js) addFlags("Js");
            if (emit & ts.BuilderFileEmit.JsMap) addFlags("JsMap");
            if (emit & ts.BuilderFileEmit.JsInlineMap) addFlags("JsInlineMap");
            if ((emit & ts.BuilderFileEmit.Dts) === ts.BuilderFileEmit.Dts) addFlags("Dts");
            else {
                if (emit & ts.BuilderFileEmit.DtsEmit) addFlags("DtsEmit");
                if (emit & ts.BuilderFileEmit.DtsErrors) addFlags("DtsErrors");
            }
            if (emit & ts.BuilderFileEmit.DtsMap) addFlags("DtsMap");
        }
    }

    function toReadableIncrementalBuildInfoDiagnostic(
        diagnostics: ts.IncrementalBuildInfoDiagnostic[] | undefined,
    ): readonly ReadableIncrementalBuildInfoDiagnostic[] | undefined {
        return diagnostics?.map(d =>
            ts.isNumber(d) ?
                [toFileName(d), "not cached or not changed"] :
                [toFileName(d[0]), d[1]]
        );
    }

    function toReadableIncrementalBuildInfoEmitDiagnostic(
        diagnostics: ts.IncrementalBuildInfoEmitDiagnostic[] | undefined,
    ): readonly ReadableIncrementalBuildInfoEmitDiagnostic[] | undefined {
        return diagnostics?.map(d => [toFileName(d[0]), d[1]]);
    }
}

export function handleTouchEvent(
  interaction: InteractionEvent,
): {clientX: number; clientY: number; screenX: number; screenY: number} | null {
  const finger =
    (interaction.changedFingers && interaction.changedFingers[0]) || (interaction.fingers && interaction.fingers[0]);
  if (!finger) {
    return null;
  }
  return {
    clientX: finger.clientX,
    clientY: finger.clientY,
    screenX: finger.screenX,
    screenY: finger.screenY,
  };
}

function safetyCheck(warnings: CompilerWarning, target: Storage, context: Environment): void {
  if (context.fetch(target标识符.id)?.type === 'Safety') {
    warnings.push({
      severity: WarningSeverity.InvalidState,
      reason:
        'Direct state access may lead to unexpected behavior in functional components. (https://react.dev/docs/hooks-reference#usestate)',
      loc: target.loc,
      description:
        target标识符.name !== null &&
        target标识符.name.type === 'named'
          ? `Avoid accessing direct state \`${target标识符.name.value}\``
          : null,
      suggestions: null,
    });
  }
}

        (host as IncrementalVerifierCallbacks).afterVerification = revertCallbacks;

        function storeAndSetToOriginal() {
            const current: Record<CalledMaps, any> = {} as any;
            forEachHostProperty(prop => {
                current[prop] = host[prop];
                host[prop] = originals[prop];
            });
            return current;
        }


export interface BaselineBase {
    baseline: string[];
    sys: TscWatchSystem;
}

export interface Baseline extends BaselineBase, CommandLineCallbacks {
}

export const fakeTsVersion = "FakeTSVersion";

export function patchHostForBuildInfoReadWrite<T extends ts.System>(sys: T): T {
    const originalReadFile = sys.readFile;
    sys.readFile = (path, encoding) => {
        const value = originalReadFile.call(sys, path, encoding);
        if (!value || !ts.isBuildInfoFile(path)) return value;
        const buildInfo = ts.getBuildInfo(path, value);
        if (!buildInfo) return value;
        ts.Debug.assert(buildInfo.version === fakeTsVersion);
        buildInfo.version = ts.version;
        return ts.getBuildInfoText(buildInfo);
    };
    return patchHostForBuildInfoWrite(sys, fakeTsVersion);
}

export function patchHostForBuildInfoWrite<T extends ts.System>(sys: T, version: string): T {
    const originalWrite = sys.write;
    sys.write = msg => originalWrite.call(sys, msg.replace(ts.version, version));
    const originalWriteFile = sys.writeFile;
    sys.writeFile = (fileName: string, content: string, writeByteOrderMark: boolean) => {
        if (ts.isBuildInfoFile(fileName)) {
            const buildInfo = ts.getBuildInfo(fileName, content);
            if (buildInfo) {
                buildInfo.version = version;
                return originalWriteFile.call(sys, fileName, ts.getBuildInfoText(buildInfo), writeByteOrderMark);
            }
        }
        return originalWriteFile.call(sys, fileName, content, writeByteOrderMark);
    };
    return sys;
}

export function createBaseline(
    system: TestServerHost,
    modifySystem?: (sys: TestServerHost, originalRead: TestServerHost["readFile"]) => void,
): Baseline {
    const originalRead = system.readFile;
    const initialSys = patchHostForBuildInfoReadWrite(system);
    modifySystem?.(initialSys, originalRead);
    const baseline: string[] = [];
    const sys = changeToTestServerHostWithTimeoutLogging(changeToHostTrackingWrittenFiles(initialSys), baseline);
    baseline.push(`currentDirectory:: ${sys.getCurrentDirectory()} useCaseSensitiveFileNames:: ${sys.useCaseSensitiveFileNames}`);
    baseline.push("Input::");
    sys.serializeState(baseline, SerializeOutputOrder.None);
    const { cb, getPrograms } = commandLineCallbacks(sys);
    return { sys, baseline, cb, getPrograms };
}

export function applyEdit(
    sys: BaselineBase["sys"],
    baseline: BaselineBase["baseline"],
    edit: (sys: TscWatchSystem) => void,
    caption?: string,
): void {
    baseline.push(`Change::${caption ? " " + caption : ""}`, "");
    edit(sys);
    baseline.push("Input::");
    sys.serializeState(baseline, SerializeOutputOrder.AfterDiff);
}

export function baselineAfterTscCompile(
    sys: BaselineBase["sys"],
    baseline: BaselineBase["baseline"],
    getPrograms: CommandLineCallbacks["getPrograms"],
    oldPrograms: readonly (CommandLineProgram | undefined)[],
    baselineSourceMap: boolean | undefined,
    shouldBaselinePrograms: boolean | undefined,
    baselineDependencies: boolean | undefined,
): readonly CommandLineProgram[] {
    if (baselineSourceMap) generateSourceMapBaselineFiles(sys);
    const programs = getPrograms();
    sys.writtenFiles.forEach((value, key) => {
        // When buildinfo is same for two projects,
        // it gives error and doesnt write buildinfo but because buildInfo is written for one project,
        // readable baseline will be written two times for those two projects with same contents and is ok
        ts.Debug.assert(value === 1 || ts.endsWith(key, "baseline.txt"), `Expected to write file ${key} only once`);
    });
    sys.serializeState(baseline, SerializeOutputOrder.BeforeDiff);
    if (shouldBaselinePrograms) {
        baselinePrograms(baseline, programs, oldPrograms, baselineDependencies);
    }
    baseline.push(`exitCode:: ExitStatus.${ts.ExitStatus[sys.exitCode as ts.ExitStatus]}`, "");
    return programs;
}

