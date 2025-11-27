import Link from 'next/link';
import React, { memo } from 'react';
import ReactMarkdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { CodeBlock } from './code-block';

/**
 * Decode HTML entities and fix newline encoding for markdown rendering.
 * When text is saved/loaded from database, \n can become literal "n" characters.
 */
function decodeAndNormalizeText(text: string): string {
	let decoded = text;
	
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

	// Fix newline encoding: "nn" followed by capital letter = paragraph break
	// e.g., "Nov 28):nnElectronics" -> "Nov 28):\n\nElectronics"
	decoded = decoded.replace(/nn(?=[A-Z])/g, '\n\n');
	
	// Fix "n- " (with space after) = list item marker
	// This pattern is safe because "n- " with space is almost always a list marker
	// Words like "amazon-prime" don't have a space after the hyphen
	decoded = decoded.replace(/n- /g, '\n- ');

	return decoded;
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
	// Decode HTML entities and fix newline encoding before rendering
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
