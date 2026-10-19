// Stands in for the Docusaurus hook, which needs the site context that a unit
// test does not have. The '/base' prefix shows that a URL went through it.
const withBaseUrl = (url) => `/base${url}`;

export const useBaseUrlUtils = () => ({ withBaseUrl });

const useBaseUrl = (url) => withBaseUrl(url);

export default useBaseUrl;
