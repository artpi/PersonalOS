export type Config = {
	site_title: string;
	api_url: string;
	wp_admin_url: string;
	rest_api_url: string;
	nonce: string;
};

declare global {
	interface Window {
		config: Config;
	}
}

// Export {} to treat this file as a module and make global augmentations apply.
export {};
