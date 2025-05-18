type Config = {
    site_title: string;
	api_url: string;
	rest_api_url:string;
}

interface Window {
  config: Config;
}
