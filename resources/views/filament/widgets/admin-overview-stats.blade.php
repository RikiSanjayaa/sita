<x-filament-widgets::widget
    class="sita-admin-overview"
    role="region"
    aria-label="Ringkasan akademik"
    x-data="{
        media: null,
        onChange: null,
        init() {
            this.media = window.matchMedia('(min-width: 768px)');
            this.onChange = () => this.arrange();
            this.media.addEventListener('change', this.onChange);
            this.$nextTick(() => this.arrange());
        },
        arrange() {
            const queue = this.$el.parentElement.querySelector('.sita-admin-actions');
            if (!queue) return;
            if (this.media.matches) queue.before(this.$el);
            else queue.after(this.$el);
        },
        destroy() {
            this.media.removeEventListener('change', this.onChange);
        },
    }"
>
    {{ $this->content }}
</x-filament-widgets::widget>
