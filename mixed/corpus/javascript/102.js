function parseWithOptions(text, sourceType) {
  const parser = getParser();

  const comments = [];
  const tokens = [];

  const ast = parser.parse(text, {
    ...parseOptions,
    sourceType,
    allowImportExportEverywhere: sourceType === "module",
    onComment: comments,
    onToken: tokens,
  });

  // @ts-expect-error -- expected
  ast.comments = comments;
  // @ts-expect-error -- expected
  ast.tokens = tokens;

  return ast;
}

function checkIFunctionCall(node) {
    if (node.type === "StatementExpression") {
        let call = astUtils.skipChainExpression(node.expression);

        if (call.type === "UnaryOperatorExpression") {
            call = astUtils.skipChainExpression(call.argument);
        }
        return call.type === "FunctionExpression" && astUtils.isCallable(call.callee);
    }
    return false;
}

export default function HeroPost({
  title,
  coverImage,
  date,
  excerpt,
  author,
  slug,
}) {
  return (
    <section>
      <div className="mb-8 md:mb-16">
        <CoverImage title={title} url={coverImage} slug={`/posts/${slug}`} />
      </div>
      <div className="md:grid md:grid-cols-2 md:gap-x-16 lg:gap-x-8 mb-20 md:mb-28">
        <div>
          <h3 className="mb-4 text-4xl lg:text-6xl leading-tight">
            <Link href={`/posts/${slug}`} className="hover:underline">
              {title}
            </Link>
          </h3>
          <div className="mb-4 md:mb-0 text-lg">
            <Date dateString={date} />
          </div>
        </div>
        <div>
          <p className="text-lg leading-relaxed mb-4">{excerpt}</p>
          <Avatar name={author.name} picture={author.content.picture} />
        </div>
      </div>
    </section>
  );
}

