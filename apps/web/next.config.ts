import type { NextConfig } from "next";
import createMDX from "@next/mdx";

const nextConfig: NextConfig = {
  pageExtensions: ["ts", "tsx", "js", "jsx", "md", "mdx"],
  // Share links are `/g/:code` only; these paths are common mistakes (games URL shape, or API spelling as a page).
  async redirects() {
    return [
      { source: "/g/:code/analysis", destination: "/g/:code", permanent: false },
      { source: "/g/:code/analyse", destination: "/g/:code", permanent: false },
    ];
  },
};

const withMDX = createMDX({});

export default withMDX(nextConfig);
