document.documentElement.classList.add('js');
const menuButton = document.querySelector('.menu-toggle');
const siteHeader = document.querySelector('.site-header');
function closeMenu() {
    if (!menuButton || !siteHeader) return;
    siteHeader.dataset.open = 'false';
    menuButton.setAttribute('aria-expanded', 'false');
}
menuButton?.addEventListener('click', () => {
    const open = menuButton.getAttribute('aria-expanded') !== 'true';
    siteHeader.dataset.open = String(open);
    menuButton.setAttribute('aria-expanded', String(open));
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && siteHeader?.dataset.open === 'true') {
        closeMenu();
        menuButton.focus();
    }
});
const service = document.getElementById('service_type');
if (service) {
    const businessFields = document.getElementById('business-fields');
    function updateService() {
        const business = ['commercial', 'care_home'].includes(service.value);
        businessFields.hidden = !business;
        ['organisation', 'site_details'].forEach(id => {
            const field = document.getElementById(id);
            field.required = business;
            field.disabled = !business;
        });
    }
    service.addEventListener('change', updateService);
    updateService();
    const contact = document.getElementById('preferred_contact');
    function updateContact() { document.getElementById('phone').required = contact.value === 'phone'; }
    contact.addEventListener('change', updateContact);
    updateContact();
}
const summary = document.getElementById('error-summary');
summary?.focus();
document.getElementById('enquiry-confirmation')?.focus();
