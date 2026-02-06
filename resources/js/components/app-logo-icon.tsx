import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M2.5 9.25L12 4.75L21.5 9.25L12 13.75L2.5 9.25Z"
                fill="currentColor"
            />
            <path
                d="M6.5 12.2V15.8C6.5 18.2 8.95 20 12 20C15.05 20 17.5 18.2 17.5 15.8V12.2L12 14.9L6.5 12.2Z"
                fill="currentColor"
            />
            <path
                d="M21.5 9.25V15.6"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <circle cx="21.5" cy="17.4" r="1.2" fill="currentColor" />
        </svg>
    );
}
