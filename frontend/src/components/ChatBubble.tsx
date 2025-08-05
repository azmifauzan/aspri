import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

type ChatBubbleProps = {
  side: "left" | "right";    // posisi gelembung
  text: string;
};

export default function ChatBubble({ side, text }: ChatBubbleProps) {
  const isLeft = side === "left";

  return (
    <div className={`flex ${isLeft ? "justify-start" : "justify-end"}`}>
      <div
        className={`max-w-[70%] rounded-lg p-3 text-sm leading-relaxed shadow
          ${isLeft
            ? "bg-white dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200"
            : "bg-brand text-white"
          }`}
      >
        {isLeft ? (
          // Render markdown for assistant messages (left side)
          <div className="prose prose-sm max-w-none dark:prose-invert
            prose-headings:text-zinc-800 dark:prose-headings:text-zinc-200 prose-headings:font-semibold prose-headings:mt-4 prose-headings:mb-2
            prose-p:text-zinc-800 dark:prose-p:text-zinc-200 prose-p:my-2 prose-p:leading-relaxed
            prose-strong:text-zinc-900 dark:prose-strong:text-zinc-100 prose-strong:font-semibold
            prose-em:text-zinc-700 dark:prose-em:text-zinc-300 prose-em:italic
            prose-code:bg-zinc-100 dark:prose-code:bg-zinc-700 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:text-zinc-800 dark:prose-code:text-zinc-200 prose-code:text-xs
            prose-pre:bg-zinc-100 dark:prose-pre:bg-zinc-700 prose-pre:p-3 prose-pre:rounded-lg prose-pre:overflow-x-auto prose-pre:text-sm
            prose-ul:my-2 prose-ol:my-2 prose-li:my-1 prose-li:text-zinc-800 dark:prose-li:text-zinc-200
            prose-blockquote:border-l-4 prose-blockquote:border-zinc-300 dark:prose-blockquote:border-zinc-600 prose-blockquote:pl-4 prose-blockquote:italic prose-blockquote:text-zinc-700 dark:prose-blockquote:text-zinc-300
            prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-a:underline hover:prose-a:text-blue-800 dark:hover:prose-a:text-blue-300
            prose-table:border-collapse prose-th:border prose-th:border-zinc-300 dark:prose-th:border-zinc-600 prose-th:bg-zinc-50 dark:prose-th:bg-zinc-700 prose-th:p-2
            prose-td:border prose-td:border-zinc-300 dark:prose-td:border-zinc-600 prose-td:p-2"
          >
            <ReactMarkdown 
              remarkPlugins={[remarkGfm]}
              components={{
                // Custom components for better styling
                h1: ({ children }) => <h1 className="text-lg font-bold mb-2 mt-4 first:mt-0">{children}</h1>,
                h2: ({ children }) => <h2 className="text-base font-semibold mb-2 mt-3 first:mt-0">{children}</h2>,
                h3: ({ children }) => <h3 className="text-sm font-semibold mb-1 mt-2 first:mt-0">{children}</h3>,
                p: ({ children }) => <p className="mb-2 last:mb-0">{children}</p>,
                ul: ({ children }) => <ul className="list-disc list-inside mb-2 space-y-1">{children}</ul>,
                ol: ({ children }) => <ol className="list-decimal list-inside mb-2 space-y-1">{children}</ol>,
                li: ({ children }) => <li className="ml-2">{children}</li>,
                code: ({ children, className }) => {
                  const isInline = !className;
                  return isInline ? (
                    <code className="bg-zinc-100 dark:bg-zinc-700 px-1 py-0.5 rounded text-xs font-mono">
                      {children}
                    </code>
                  ) : (
                    <code className={className}>{children}</code>
                  );
                },
                pre: ({ children }) => (
                  <pre className="bg-zinc-100 dark:bg-zinc-700 p-3 rounded-lg overflow-x-auto text-sm font-mono mb-2">
                    {children}
                  </pre>
                ),
                blockquote: ({ children }) => (
                  <blockquote className="border-l-4 border-zinc-300 dark:border-zinc-600 pl-4 italic text-zinc-700 dark:text-zinc-300 mb-2">
                    {children}
                  </blockquote>
                ),
              }}
            >
              {text}
            </ReactMarkdown>
          </div>
        ) : (
          // Render plain text for user messages (right side)
          <span className="whitespace-pre-wrap">{text}</span>
        )}
      </div>
    </div>
  );
}