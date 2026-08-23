const navigationScript = document.currentScript;
const rootPath = navigationScript?.dataset.root || '';
const currentPage = navigationScript?.dataset.page || '';

const navigationItems = [
    { section: 'WORKSPACE' },
    { id: 'assistant', label: 'Assistant', href: 'index.html', icon: 'sparkles.svg' },
    { id: 'employees', label: 'Employees', href: 'pages/employees.html', icon: 'users.svg' },
    { id: 'birthday-cards', label: 'Birthday cards', href: 'pages/birthday-cards.html', icon: 'image.svg', subpage: true },
    { id: 'awards-certificates', label: 'Awards & Certificates', href: 'pages/awards-certificates.html', icon: 'award.svg', subpage: true },
    { id: 'farewell-card', label: 'Farewell cards', href: 'pages/farewell-card.html', icon: 'send.svg', subpage: true },
    { id: 'settings', label: 'Settings', href: 'pages/settings.html', icon: 'settings.svg' },
    { section: 'GENERATE CARDS' },
    { id: 'birthday-generator', label: 'B’Day celebration card', href: 'pages/birthday-generator.html', icon: 'award.svg' },
    { id: 'event-card', label: 'Event card', href: 'pages/event-card.html', icon: 'clock.svg' }
];

function createSidebar() {
    if (document.querySelector('.sidebar')) return;

    const sidebar = document.createElement('aside');
    sidebar.className = 'sidebar';

    const brand = document.createElement('div');
    brand.className = 'brand';
    brand.innerHTML = '🎂 <b>Birthday Studio</b><small>Employee celebration hub</small>';

    const navigation = document.createElement('nav');
    navigation.setAttribute('aria-label', 'Primary navigation');

    navigationItems.forEach((item) => {
        if (item.section) {
            const heading = document.createElement('span');
            heading.textContent = item.section;
            navigation.appendChild(heading);
            return;
        }

        const link = document.createElement('a');
        link.href = `${rootPath}${item.href}`;
        if (item.subpage) link.classList.add('subpage');
        if (item.id === currentPage) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        }

        const icon = document.createElement('img');
        icon.src = `${rootPath}assets/icons/${item.icon}`;
        icon.alt = '';
        link.append(icon, document.createTextNode(item.label));
        navigation.appendChild(link);
    });

    sidebar.append(brand, navigation);
    document.body.prepend(sidebar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', createSidebar);
} else {
    createSidebar();
}
