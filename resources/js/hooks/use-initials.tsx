import { getInitials } from '@/lib/initials';

export function useInitials(): typeof getInitials {
    return getInitials;
}
