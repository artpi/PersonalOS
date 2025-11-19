import Link from 'next/link';
import React, { memo } from 'react';
import ReactMarkdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { CodeBlock } from './code-block';

/**
 * Decode HTML entities and normalize text for markdown rendering
 * Handles cases where newlines are encoded as "n" or "nn" characters
 */
function decodeAndNormalizeText(text: string): string {
	let decoded = text;
	
	// Remove HTML tags but preserve their content
	decoded = decoded.replace(/<[^>]*>/g, '');
	
	// Replace encoded newlines: "nn" = double newline, "n" = single newline
	// Pattern: "nn" followed by "-" or space, or "n" followed by "-" or space
	// Replace "nn" first (double newline) before single "n" to avoid double replacement
	decoded = decoded.replace(/nn(?=\s*-)/g, '\n\n');
	decoded = decoded.replace(/n(?=\s*-)/g, '\n');
	
	// Also handle cases where "nn" or "n" appears at end of line or before other whitespace
	decoded = decoded.replace(/nn(?=\s)/g, '\n\n');
	decoded = decoded.replace(/(?<!\n)n(?=\s)/g, '\n');
	
	// Decode common HTML entities
	decoded = decoded
		.replace(/&nbsp;/g, ' ')
		.replace(/&amp;/g, '&')
		.replace(/&lt;/g, '<')
		.replace(/&gt;/g, '>')
		.replace(/&quot;/g, '"')
		.replace(/&#39;/g, "'")
		.replace(/&apos;/g, "'");

	// If we're in browser, use DOM to decode remaining HTML entities
	if (typeof window !== 'undefined') {
		const textarea = document.createElement('textarea');
		textarea.innerHTML = decoded;
		decoded = textarea.value;
	}

	return decoded.trim();
}

const components: Partial<Components> = {
  // @ts-expect-error
  code: CodeBlock,
  pre: ({ children }) => <>{children}</>,
  ol: ({ node, children, ...props }) => {
    return (
      <ol className="list-decimal list-outside ml-4" {...props}>
        {children}
      </ol>
    );
  },
  li: ({ node, children, ...props }) => {
    return (
      <li className="py-1" {...props}>
        {children}
      </li>
    );
  },
  ul: ({ node, children, ...props }) => {
    return (
      <ul className="list-decimal list-outside ml-4" {...props}>
        {children}
      </ul>
    );
  },
  strong: ({ node, children, ...props }) => {
    return (
      <span className="font-semibold" {...props}>
        {children}
      </span>
    );
  },
  a: ({ node, children, ...props }) => {
    return (
      // @ts-expect-error
      <Link
        className="text-blue-500 hover:underline"
        target="_blank"
        rel="noreferrer"
        {...props}
      >
        {children}
      </Link>
    );
  },
  h1: ({ node, children, ...props }) => {
    return (
      <h1 className="text-3xl font-semibold mt-6 mb-2" {...props}>
        {children}
      </h1>
    );
  },
  h2: ({ node, children, ...props }) => {
    return (
      <h2 className="text-2xl font-semibold mt-6 mb-2" {...props}>
        {children}
      </h2>
    );
  },
  h3: ({ node, children, ...props }) => {
    return (
      <h3 className="text-xl font-semibold mt-6 mb-2" {...props}>
        {children}
      </h3>
    );
  },
  h4: ({ node, children, ...props }) => {
    return (
      <h4 className="text-lg font-semibold mt-6 mb-2" {...props}>
        {children}
      </h4>
    );
  },
  h5: ({ node, children, ...props }) => {
    return (
      <h5 className="text-base font-semibold mt-6 mb-2" {...props}>
        {children}
      </h5>
    );
  },
  h6: ({ node, children, ...props }) => {
    return (
      <h6 className="text-sm font-semibold mt-6 mb-2" {...props}>
        {children}
      </h6>
    );
  },
};

const remarkPlugins = [remarkGfm];

const NonMemoizedMarkdown = ({ children }: { children: string }) => {
	// Decode HTML entities and normalize the text before rendering
	const normalizedText = decodeAndNormalizeText(children);

	return (
		<ReactMarkdown remarkPlugins={remarkPlugins} components={components}>
			{normalizedText}
		</ReactMarkdown>
	);
};

export const Markdown = memo(
  NonMemoizedMarkdown,
  (prevProps, nextProps) => prevProps.children === nextProps.children,
);
