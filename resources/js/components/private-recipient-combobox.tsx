import { Check, Search, UserPlus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

export type PrivateRecipientOption = {
    id: number;
    name: string;
    subtitle: string | null;
    avatar: string | null;
    profileUrl: string | null;
    roleLabel: string;
    identifier: string | null;
    searchText: string;
};

type PrivateRecipientComboboxProps = {
    recipients: PrivateRecipientOption[];
    value: string;
    onValueChange: (value: string) => void;
    onSubmit: () => void;
    disabled?: boolean;
    placeholder?: string;
};

export function PrivateRecipientCombobox({
    recipients,
    value,
    onValueChange,
    onSubmit,
    disabled = false,
    placeholder = 'Cari nama, NIM, atau NIK',
}: PrivateRecipientComboboxProps) {
    const getInitials = useInitials();
    const rootRef = useRef<HTMLDivElement | null>(null);
    const [query, setQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);

    const selectedRecipient = recipients.find(
        (recipient) => String(recipient.id) === value,
    );

    const filteredRecipients = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();

        if (normalizedQuery === '') {
            return recipients.slice(0, 12);
        }

        return recipients
            .filter((recipient) =>
                recipient.searchText.toLowerCase().includes(normalizedQuery),
            )
            .slice(0, 12);
    }, [query, recipients]);

    useEffect(() => {
        function closeOnOutsideClick(event: MouseEvent) {
            if (
                rootRef.current &&
                !rootRef.current.contains(event.target as Node)
            ) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', closeOnOutsideClick);

        return () => {
            document.removeEventListener('mousedown', closeOnOutsideClick);
        };
    }, []);

    function selectRecipient(recipient: PrivateRecipientOption) {
        onValueChange(String(recipient.id));
        setQuery(recipient.name);
        setIsOpen(false);
    }

    return (
        <div ref={rootRef} className="relative flex min-w-0 flex-1 gap-2">
            <div className="relative min-w-0 flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onFocus={() => setIsOpen(true)}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setIsOpen(true);
                        if (selectedRecipient) {
                            onValueChange('');
                        }
                    }}
                    placeholder={
                        selectedRecipient ? selectedRecipient.name : placeholder
                    }
                    className="h-9 pl-9"
                    disabled={disabled}
                    onKeyDown={(event) => {
                        if (
                            event.key === 'Enter' &&
                            filteredRecipients.length === 1
                        ) {
                            event.preventDefault();
                            selectRecipient(filteredRecipients[0]);
                        }
                    }}
                />

                {isOpen ? (
                    <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-72 overflow-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                        {filteredRecipients.length > 0 ? (
                            filteredRecipients.map((recipient) => {
                                const isSelected =
                                    String(recipient.id) === value;

                                return (
                                    <button
                                        key={recipient.id}
                                        type="button"
                                        className={cn(
                                            'flex w-full items-center gap-2 rounded-sm px-2 py-2 text-left text-sm transition-colors outline-none hover:bg-accent hover:text-accent-foreground',
                                            isSelected &&
                                                'bg-accent text-accent-foreground',
                                        )}
                                        onClick={() =>
                                            selectRecipient(recipient)
                                        }
                                    >
                                        <Avatar className="size-7 shrink-0 border">
                                            <AvatarImage
                                                src={
                                                    recipient.avatar ??
                                                    undefined
                                                }
                                                alt={recipient.name}
                                            />
                                            <AvatarFallback className="bg-primary/10 text-[10px] text-primary">
                                                {getInitials(recipient.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-medium">
                                                {recipient.name}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {[
                                                    recipient.identifier,
                                                    recipient.subtitle,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' - ')}
                                            </span>
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="shrink-0 rounded-full text-[10px]"
                                        >
                                            {recipient.roleLabel}
                                        </Badge>
                                        {isSelected ? (
                                            <Check className="size-4 shrink-0 text-primary" />
                                        ) : null}
                                    </button>
                                );
                            })
                        ) : (
                            <div className="px-3 py-6 text-center text-sm text-muted-foreground">
                                Kontak tidak ditemukan
                            </div>
                        )}
                    </div>
                ) : null}
            </div>

            <Button
                type="button"
                size="icon"
                onClick={onSubmit}
                disabled={disabled || value === ''}
            >
                <UserPlus className="size-4" />
            </Button>
        </div>
    );
}
