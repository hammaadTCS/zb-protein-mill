import type { ImgHTMLAttributes } from 'react';

/** The "ZB" monogram tile (docs/brand.md). Never the raster logo. */
export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return <img src="/favicon.svg" alt="" {...props} />;
}
