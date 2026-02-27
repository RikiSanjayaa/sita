import { Download } from 'lucide-react';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';

export type ChatMessagePayload = {
  id: number | string;
  author: string;
  message: string;
  time: string;
  type: string;
  documentName: string | null;
  documentUrl: string | null;
};

interface ChatBubbleProps {
  message: ChatMessagePayload;
  isMe: boolean;
}

function initials(name: string) {
  return name
    .split(' ')
    .map((chunk) => chunk[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

export function ChatBubble({ message, isMe }: ChatBubbleProps) {
  if (message.type === 'document_event' || message.type === 'revision_suggestion') {
    const isRevision = message.type === 'revision_suggestion';

    return (
      <div className="animate-pop relative overflow-hidden rounded-lg border border-primary/25 bg-background p-3">
        <div className="pointer-events-none absolute inset-0 bg-primary/10" />
        <div className="relative z-10">
          <div className="text-sm font-medium text-primary">
            {isRevision ? 'File revisi dari dosen' : 'Dokumen baru diunggah'}
          </div>
          <div className="mt-1 text-sm text-primary">
            {message.message}
          </div>
          {message.documentName && (
            <div className="mt-2 rounded border bg-background p-2 text-sm max-w-[200px] truncate sm:max-w-none">
              {message.documentName}
            </div>
          )}
          <div className="mt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span className="text-xs text-muted-foreground">
              {message.author} - {message.time}
            </span>
            <Button
              size="sm"
              variant="outline"
              className="h-8 gap-2 w-full sm:w-auto"
              disabled={!message.documentUrl}
              onClick={() => {
                if (message.documentUrl) {
                  window.open(
                    message.documentUrl,
                    '_blank',
                    'noopener,noreferrer',
                  );
                }
              }}
            >
              <Download className="size-3.5" />
              Unduh
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className={`animate-pop flex ${isMe ? 'justify-end' : ''}`}>
      {!isMe && (
        <Avatar className="mt-0.5 mr-2 size-7 shrink-0">
          <AvatarFallback>{initials(message.author)}</AvatarFallback>
        </Avatar>
      )}
      <div
        className={`max-w-[85%] sm:max-w-[78%] rounded-2xl border px-3 py-2 text-sm ${isMe
            ? 'rounded-tr-sm bg-primary text-primary-foreground'
            : 'rounded-tl-sm bg-background'
          }`}
      >
        {message.documentName && (
          <div
            className={`mb-2 rounded border p-2 text-xs break-all ${isMe
                ? 'border-primary-foreground/25 bg-primary-foreground/15'
                : 'bg-muted/30'
              }`}
          >
            {message.documentName}
          </div>
        )}
        {message.message && (
          <div className="break-words">{message.message}</div>
        )}
        <div
          className={`mt-1 text-[11px] ${isMe ? 'text-primary-foreground/70' : 'text-muted-foreground'
            }`}
        >
          {message.author} - {message.time}
        </div>
      </div>
    </div>
  );
}
