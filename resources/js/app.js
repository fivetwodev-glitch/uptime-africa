import './bootstrap';

// Interactions de l'interface. Aucun secret ni clé API côté navigateur.
document.addEventListener('DOMContentLoaded', () => {
    // Menu latéral sur mobile.
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const setSidebar = (open) => {
        if (!sidebar) return;
        sidebar.classList.toggle('-translate-x-full', !open);
        backdrop?.classList.toggle('hidden', !open);
        document.querySelectorAll('[data-sidebar-toggle]').forEach((b) => b.setAttribute('aria-expanded', String(open)));
    };
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) =>
        button.addEventListener('click', () => setSidebar(sidebar?.classList.contains('-translate-x-full'))),
    );
    backdrop?.addEventListener('click', () => setSidebar(false));

    // Menu de la page publique.
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');
    toggle?.addEventListener('click', () => {
        const open = menu.classList.toggle('hidden') === false;
        toggle.setAttribute('aria-expanded', String(open));
    });

    // Confirmation avant une action destructive.
    document.querySelectorAll('form[data-confirm]').forEach((form) =>
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        }),
    );

    // Filtres appliqués dès qu'une liste déroulante change.
    document.querySelectorAll('[data-autosubmit]').forEach((field) =>
        field.addEventListener('change', () => field.form?.submit()),
    );

    // Champ « identifiant externe » visible seulement pour un fournisseur externe.
    const provider = document.querySelector('[data-provider-select]');
    const external = document.querySelector('[data-external-field]');
    const syncExternal = () => external?.classList.toggle('hidden', provider?.value === 'internal');
    provider?.addEventListener('change', syncExternal);
    syncExternal();

    // Placeholder du destinataire selon le type de canal.
    const channelType = document.querySelector('[data-channel-type]');
    const destination = document.querySelector('[data-channel-destination]');
    const syncChannel = () => {
        if (!channelType || !destination) return;
        destination.placeholder = channelType.value === 'whatsapp' ? '+2250701020304' : 'alertes@monagence.ci';
        destination.type = channelType.value === 'whatsapp' ? 'tel' : 'email';
    };
    channelType?.addEventListener('change', syncChannel);
    syncChannel();
});
