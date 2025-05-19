export type Config = {
	site_title: string;
	wp_admin_url: string;
	rest_api_url: string;
	nonce: string;
	projects: PARAItem[];
	starred: PARAItem[];
};

// Define PARAItem interface
export interface PARAItem {
	id: string | number; // Allow number for IDs from backend if necessary
	name: string;
	// The icon is a React Functional Component that takes an optional className
	// This cannot be directly represented in a .d.ts file for global window object if icons are components.
	// For simplicity in window.config, we might store icon *names* or skip them in the global config,
	// and map them to actual components in the frontend.
	// However, if mock data is defined in JS/TS, it can hold components directly.
	// For now, let's assume icon is optional and its type will be handled by consuming components if it's a component.
	icon?: any; // Placeholder for icon, can be string (for lookup) or React.FC
}

declare global {
	interface Window {
		config: Config;
	}
}

// Export {} to treat this file as a module and make global augmentations apply.
export {};
