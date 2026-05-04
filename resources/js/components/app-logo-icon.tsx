import { type CSSProperties, type HTMLAttributes } from 'react';

const maskStyle: CSSProperties = {
    WebkitMaskImage: "url('/Logo-UBG.svg')",
    WebkitMaskPosition: 'center',
    WebkitMaskRepeat: 'no-repeat',
    WebkitMaskSize: 'contain',
    backgroundColor: 'currentColor',
    maskImage: "url('/Logo-UBG.svg')",
    maskPosition: 'center',
    maskRepeat: 'no-repeat',
    maskSize: 'contain',
};

export default function AppLogoIcon({
    className,
    style,
    ...props
}: HTMLAttributes<HTMLSpanElement>) {
    return (
        <span
            {...props}
            aria-hidden="true"
            className={['inline-block shrink-0', className]
                .filter(Boolean)
                .join(' ')}
            style={{ ...maskStyle, ...style }}
        />
    );
}
