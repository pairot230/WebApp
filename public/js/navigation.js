(() => {
    const toggle = document.getElementById('menu-toggle');
    const navigation = document.getElementById('main-navigation');
    if (!toggle || !navigation) return;

    const mobile = window.matchMedia('(max-width: 600px)');
    const setExpanded = (expanded) => {
        navigation.hidden = !expanded;
        toggle.setAttribute('aria-expanded', String(expanded));
    };
    const updateLayout = () => {
        toggle.hidden = !mobile.matches;
        setExpanded(!mobile.matches);
    };
    toggle.addEventListener('click', () => setExpanded(navigation.hidden));
    navigation.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.matches) {
            setExpanded(false);
            toggle.focus();
        }
    });
    mobile.addEventListener('change', updateLayout);
    updateLayout();

    document.querySelectorAll('.table-wrapper').forEach((table) => {
        table.tabIndex = 0;
        table.setAttribute('role', 'region');
        table.setAttribute('aria-label', 'ตารางข้อมูล เลื่อนซ้ายและขวาเพื่อดูข้อมูลเพิ่มเติม');
    });
})();
