import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merges Tailwind classes conditionally with proper precedence.
 */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}
