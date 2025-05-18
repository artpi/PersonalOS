export type Config = {
	site_title: string;
	api_url: string;
	rest_api_url: string;
};

declare global {
	interface Window {
		config: Config;
	}
}

// Export {} to treat this file as a module and make global augmentations apply.
export {};
