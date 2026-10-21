export function resolvePortfolioMediaUrl(value: unknown): string {
    if (typeof value !== 'string') return '';

    const mediaUrl = value.trim();
    if (!mediaUrl) return '';

    const absoluteStorageUrl = mediaUrl.match(/^https?:\/\/[^/]+(\/storage\/[^?#]*([?#].*)?)$/i);
    if (absoluteStorageUrl) return absoluteStorageUrl[1];

    if (mediaUrl.startsWith('/')) return mediaUrl;
    if (mediaUrl.startsWith('storage/')) return `/${mediaUrl}`;
    if (mediaUrl.startsWith('users/')) return `/storage/${mediaUrl}`;

    return mediaUrl;
}
