(() => {
    const mobileMenuToggle = document.getElementById("mobile-menu-toggle");
    const mobileMenu = document.getElementById("mobile-menu");
    const mobileMenuOverlay = document.getElementById("mobile-menu-overlay");
    const mobileLinks = document.querySelectorAll(".mobile-nav-link");

    if (mobileMenuToggle && mobileMenu && mobileMenuOverlay) {
        let isMenuOpen = false;

        const toggleMobileMenu = (show) => {
            isMenuOpen = show;

            if (show) {
                mobileMenuToggle.classList.add("is-active");
                mobileMenuOverlay.classList.remove("hidden-overlay");
                mobileMenuOverlay.classList.add("active-overlay");
                mobileMenu.classList.remove("hidden-menu");
                mobileMenu.classList.add("active-menu");
                document.body.style.overflow = "hidden";
                return;
            }

            mobileMenuToggle.classList.remove("is-active");
            mobileMenuOverlay.classList.remove("active-overlay");
            mobileMenuOverlay.classList.add("hidden-overlay");
            mobileMenu.classList.remove("active-menu");
            mobileMenu.classList.add("hidden-menu");
            document.body.style.overflow = "";
        };

        mobileMenuToggle.addEventListener("click", () => {
            toggleMobileMenu(!isMenuOpen);
        });

        mobileMenuOverlay.addEventListener("click", () => toggleMobileMenu(false));

        mobileLinks.forEach((link) => {
            link.addEventListener("click", () => {
                toggleMobileMenu(false);
            });
        });
    }

    const revealItems = document.querySelectorAll("[data-reveal]");
    if (!revealItems.length) {
        return;
    }

    if (!("IntersectionObserver" in window) || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        revealItems.forEach((item) => item.classList.add("is-visible"));
        return;
    }

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add("is-visible");
            revealObserver.unobserve(entry.target);
        });
    }, { threshold: 0.12 });

    revealItems.forEach((item) => revealObserver.observe(item));
})();
