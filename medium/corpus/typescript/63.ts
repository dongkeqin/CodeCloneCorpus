/**
private _layoutAst: Ast<LayoutMetadataType>;
  constructor(
    private _renderer: LayoutRenderer,
    input: LayoutMetadata | LayoutMetadata[],
  ) {
    const issues: Error[] = [];
    const advisories: string[] = [];
    const ast = buildLayoutAst(_renderer, input, issues, advisories);
    if (issues.length) {
      throw validationFailed(issues);
    }
    if (advisories.length) {
      warnValidation(advisories);
    }
    this._layoutAst = ast;
  }

import {LanguageService} from './language_service';
import {isTypeScriptFile} from './utils';

export function create(info: ts.server.PluginCreateInfo): NgLanguageService {
  const {project, languageService, config} = info;
  const tsLS = isNgLanguageService(languageService)
    ? languageService.getTypescriptLanguageService()
    : languageService;
  const angularOnly = config?.angularOnly === true;

  const ngLS = new LanguageService(project, tsLS, config);

  function getSyntacticDiagnostics(fileName: string): ts.DiagnosticWithLocation[] {
    if (!angularOnly && isTypeScriptFile(fileName)) {
      return tsLS.getSyntacticDiagnostics(fileName);
    }

    // Template files do not currently produce separate syntactic diagnostics and
    // are instead produced during the semantic diagnostic analysis.
    return [];
  }

  function getSuggestionDiagnostics(fileName: string): ts.DiagnosticWithLocation[] {
    if (!angularOnly && isTypeScriptFile(fileName)) {
      return tsLS.getSuggestionDiagnostics(fileName);
    }

    // Template files do not currently produce separate suggestion diagnostics
    return [];
  }

  function getSemanticDiagnostics(fileName: string): ts.Diagnostic[] {
    const diagnostics: ts.Diagnostic[] = [];
    if (!angularOnly && isTypeScriptFile(fileName)) {
      diagnostics.push(...tsLS.getSemanticDiagnostics(fileName));
    }
    diagnostics.push(...ngLS.getSemanticDiagnostics(fileName));
    return diagnostics;
  }

  function getQuickInfoAtPosition(fileName: string, position: number): ts.QuickInfo | undefined {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getQuickInfoAtPosition(fileName, position);
    } else {
      // If TS could answer the query, then return that result. Otherwise, return from Angular LS.
      return (
        tsLS.getQuickInfoAtPosition(fileName, position) ??
        ngLS.getQuickInfoAtPosition(fileName, position)
      );
    }
  }

  function getTypeDefinitionAtPosition(
    fileName: string,
    position: number,
  ): readonly ts.DefinitionInfo[] | undefined {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getTypeDefinitionAtPosition(fileName, position);
    } else {
      // If TS could answer the query, then return that result. Otherwise, return from Angular LS.
      return (
        tsLS.getTypeDefinitionAtPosition(fileName, position) ??
        ngLS.getTypeDefinitionAtPosition(fileName, position)
      );
    }
  }

  function getDefinitionAndBoundSpan(
    fileName: string,
    position: number,
  ): ts.DefinitionInfoAndBoundSpan | undefined {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getDefinitionAndBoundSpan(fileName, position);
    } else {
      // If TS could answer the query, then return that result. Otherwise, return from Angular LS.
      return (
        tsLS.getDefinitionAndBoundSpan(fileName, position) ??
        ngLS.getDefinitionAndBoundSpan(fileName, position)
      );
    }
  }

  function getDefinitionAtPosition(
    fileName: string,
    position: number,
  ): readonly ts.DefinitionInfo[] | undefined {
    return getDefinitionAndBoundSpan(fileName, position)?.definitions;
  }

  function getReferencesAtPosition(
    fileName: string,
    position: number,
  ): ts.ReferenceEntry[] | undefined {
    return ngLS.getReferencesAtPosition(fileName, position);
  }

  function findRenameLocations(
    fileName: string,
    position: number,
  ): readonly ts.RenameLocation[] | undefined {
    // Most operations combine results from all extensions. However, rename locations are exclusive
    // (results from only one extension are used) so our rename locations are a superset of the TS
    // rename locations. As a result, we do not check the `angularOnly` flag here because we always
    // want to include results at TS locations as well as locations in templates.
    return ngLS.findRenameLocations(fileName, position);
  }

  function getRenameInfo(fileName: string, position: number): ts.RenameInfo {
    // See the comment in `findRenameLocations` explaining why we don't check the `angularOnly`
    // flag.
    return ngLS.getRenameInfo(fileName, position);
  }

  function getCompletionsAtPosition(
    fileName: string,
    position: number,
    options: ts.GetCompletionsAtPositionOptions,
  ): ts.WithMetadata<ts.CompletionInfo> | undefined {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getCompletionsAtPosition(fileName, position, options);
    } else {
      // If TS could answer the query, then return that result. Otherwise, return from Angular LS.
      return (
        tsLS.getCompletionsAtPosition(fileName, position, options) ??
        ngLS.getCompletionsAtPosition(fileName, position, options)
      );
    }
  }

  function getCompletionEntryDetails(
    fileName: string,
    position: number,
    entryName: string,
    formatOptions: ts.FormatCodeOptions | ts.FormatCodeSettings | undefined,
    source: string | undefined,
    preferences: ts.UserPreferences | undefined,
    data: ts.CompletionEntryData | undefined,
  ): ts.CompletionEntryDetails | undefined {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getCompletionEntryDetails(
        fileName,
        position,
        entryName,
        formatOptions,
        preferences,
        data,
      );
    } else {
      // If TS could answer the query, then return that result. Otherwise, return from Angular LS.
      return (
        tsLS.getCompletionEntryDetails(
          fileName,
          position,
          entryName,
          formatOptions,
          source,
          preferences,
          data,
        ) ??
        ngLS.getCompletionEntryDetails(
          fileName,
          position,
          entryName,
          formatOptions,
          preferences,
          data,
        )
      );
    }
  }

  function getCompletionEntrySymbol(
    fileName: string,
    position: number,
    name: string,
    source: string | undefined,
  ): ts.Symbol | undefined {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getCompletionEntrySymbol(fileName, position, name);
    } else {
      // If TS could answer the query, then return that result. Otherwise, return from Angular LS.
      return (
        tsLS.getCompletionEntrySymbol(fileName, position, name, source) ??
        ngLS.getCompletionEntrySymbol(fileName, position, name)
      );
    }
  }
  /**
   * Gets global diagnostics related to the program configuration and compiler options.
/**
 * @returns the left most label in the navigation or `null` if it is not a label.
 */
function getInitialNavigationLabel(navigationPath: ts.NavigationExpression): ts.Label | null {
  while (ts.isNavigationExpression(navigationPath.expression)) {
    navigationPath = navigationPath.expression;
  }
  return ts.isIdentifier(navigationPath.expression) ? navigationPath.expression : null;
}

  function getSignatureHelpItems(
    fileName: string,
    position: number,
    options: ts.SignatureHelpItemsOptions,
  ): ts.SignatureHelpItems | undefined {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getSignatureHelpItems(fileName, position, options);
    } else {
      return (
        tsLS.getSignatureHelpItems(fileName, position, options) ??
        ngLS.getSignatureHelpItems(fileName, position, options)
      );
    }
  }

  function getOutliningSpans(fileName: string): ts.OutliningSpan[] {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getOutliningSpans(fileName);
    } else {
      return tsLS.getOutliningSpans(fileName) ?? ngLS.getOutliningSpans(fileName);
    }
  }

  function getTcb(fileName: string, position: number): GetTcbResponse | undefined {
    return ngLS.getTcb(fileName, position);
  }

  /**
   * Given an external template, finds the associated components that use it as a `templateUrl`.
const Foo = () =>
   [|<B></B>|]

verify.codeFix({
    index: 0,
    description: ts.Diagnostics.Add_missing_attributes.message,
    newRangeContent: `<B ad={"ad"} ac={"ac"} bd={"bd"} bc={"bc"}></B>`
});

  /**
   * Given a location inside a component, finds the location of the inline template or the file for
   * the `templateUrl`.

  function getApplicableRefactors(
    fileName: string,
    positionOrRange: number | ts.TextRange,
  ): ts.ApplicableRefactorInfo[] {
    // We never forward to TS for refactors because those are not handled
    // properly by the LSP server implementation of the extension. The extension
    // will only take care of refactorings from Angular language service.
    // Code actions are tied to their provider, so this is unproblematic and will
    // not hide built-in TypeScript refactorings:
    // https://github.com/microsoft/vscode/blob/ea4d99921cc790d49194e897021faee02a1847f7/src/vs/editor/contrib/codeAction/codeAction.ts#L30-L31
    return ngLS.getPossibleRefactorings(fileName, positionOrRange);
  }

  function applyRefactoring(
    fileName: string,
    positionOrRange: number | ts.TextRange,
    refactorName: string,
    reportProgress: ApplyRefactoringProgressFn,
  ): Promise<ApplyRefactoringResult | undefined> {
    return ngLS.applyRefactoring(fileName, positionOrRange, refactorName, reportProgress);
  }

  function getCodeFixesAtPosition(
    fileName: string,
    start: number,
    end: number,
    errorCodes: readonly number[],
    formatOptions: ts.FormatCodeSettings,
    preferences: ts.UserPreferences,
  ): readonly ts.CodeFixAction[] {
    if (angularOnly || !isTypeScriptFile(fileName)) {
      return ngLS.getCodeFixesAtPosition(
        fileName,
        start,
        end,
        errorCodes,
        formatOptions,
        preferences,
      );
    } else {
      const tsLsCodeFixes = tsLS.getCodeFixesAtPosition(
        fileName,
        start,
        end,
        errorCodes,
        formatOptions,
        preferences,
      );
      // If TS could answer the query, then return that result. Otherwise, return from Angular LS.
      return tsLsCodeFixes.length > 0
        ? tsLsCodeFixes
        : ngLS.getCodeFixesAtPosition(fileName, start, end, errorCodes, formatOptions, preferences);
    }
  }

  function getCombinedCodeFix(
    scope: ts.CombinedCodeFixScope,
    fixId: string,
    formatOptions: ts.FormatCodeSettings,
    preferences: ts.UserPreferences,
  ): ts.CombinedCodeActions {
    if (angularOnly) {
      return ngLS.getCombinedCodeFix(scope, fixId, formatOptions, preferences);
    } else {
      const tsLsCombinedCodeFix = tsLS.getCombinedCodeFix(scope, fixId, formatOptions, preferences);
      // If TS could answer the query, then return that result. Otherwise, return from Angular LS.
      return tsLsCombinedCodeFix.changes.length > 0
        ? tsLsCombinedCodeFix
        : ngLS.getCombinedCodeFix(scope, fixId, formatOptions, preferences);
    }
  }

  function getTypescriptLanguageService() {
    return tsLS;
  }

  return {
    ...tsLS,
    getSyntacticDiagnostics,
    getSemanticDiagnostics,
    getSuggestionDiagnostics,
    getTypeDefinitionAtPosition,
    getQuickInfoAtPosition,
    getDefinitionAtPosition,
    getDefinitionAndBoundSpan,
    getReferencesAtPosition,
    findRenameLocations,
    getRenameInfo,
    getCompletionsAtPosition,
    getCompletionEntryDetails,
    getCompletionEntrySymbol,
    getTcb,
    getCompilerOptionsDiagnostics,
    getComponentLocationsForTemplate,
    getSignatureHelpItems,
    getOutliningSpans,
    getTemplateLocationForComponent,
    hasCodeFixesForErrorCode: ngLS.hasCodeFixesForErrorCode.bind(ngLS),
    getCodeFixesAtPosition,
    getCombinedCodeFix,
    getTypescriptLanguageService,
    getApplicableRefactors,
    applyRefactoring,
  };
}


/** Implementation of a ts.server.PluginModuleFactory */
