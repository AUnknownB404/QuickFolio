export function handleContactFormFallback(event: Event, email: string | undefined): void {
    event.preventDefault();

    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement)) return;

    let status = form.querySelector<HTMLElement>('[data-contact-fallback-status]');
    if (!status) {
        status = document.createElement('p');
        status.dataset.contactFallbackStatus = 'true';
        status.setAttribute('role', 'status');
        form.append(status);
    }

    const recipient = email?.trim();
    if (!recipient) {
        status.textContent = 'This form is not connected yet. Please use another contact method.';
        return;
    }

    const name = form.querySelector<HTMLInputElement>('input[type="text"]')?.value.trim() ?? '';
    const senderEmail = form.querySelector<HTMLInputElement>('input[type="email"]')?.value.trim() ?? '';
    const message = form.querySelector<HTMLTextAreaElement>('textarea')?.value.trim() ?? '';
    const body = `${message}\n\n${name} (${senderEmail})`;
    const subject = `Message from ${name}`;

    window.location.href = `mailto:${encodeURIComponent(recipient)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    status.textContent = 'Opening your email app to send this message.';
}
