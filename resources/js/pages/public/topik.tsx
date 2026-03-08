import { usePage } from '@inertiajs/react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type SharedData } from '@/types';

type SemproTitleItem = {
    id: number;
    programStudi: string;
    title: string;
    summary: string;
    semproStatus: string;
    semproDate: string | null;
    advisors: Array<{
        name: string;
        label: string;
    }>;
};

type PageProps = {
    semproTitles: SemproTitleItem[];
};

export default function PublicTopicsPage() {
    const { semproTitles } = usePage<SharedData & PageProps>().props;

    return (
        <PublicLayout
            active="topik"
            title="Topik Sempro"
            description="Daftar ini menampilkan judul sempro, ringkasan proposal, dan dosen pembimbing aktif yang terhubung pada setiap topik."
        >
            <div className="grid gap-4">
                {semproTitles.length > 0 ? (
                    semproTitles.map((item) => (
                        <Card key={item.id} className="shadow-sm">
                            <CardHeader className="gap-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {item.programStudi}
                                    </Badge>
                                    <Badge className="bg-primary text-primary-foreground hover:bg-primary/90">
                                        {item.semproStatus}
                                    </Badge>
                                    {item.semproDate ? (
                                        <Badge variant="secondary">
                                            {item.semproDate}
                                        </Badge>
                                    ) : null}
                                </div>
                                <CardTitle className="max-w-4xl text-xl leading-8">
                                    {item.title}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="max-w-4xl text-sm leading-7 text-muted-foreground">
                                    {item.summary}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {item.advisors.map((advisor) => (
                                        <Badge
                                            key={`${item.id}-${advisor.label}`}
                                            variant="secondary"
                                        >
                                            {advisor.label}: {advisor.name}
                                        </Badge>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    ))
                ) : (
                    <Card className="shadow-sm">
                        <CardContent className="p-6 text-center text-sm text-muted-foreground">
                            Belum ada topik sempro yang dapat ditampilkan.
                        </CardContent>
                    </Card>
                )}
            </div>
        </PublicLayout>
    );
}
