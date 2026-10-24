<?php

namespace App\Http\Controllers\PortfolioExport;

trait PortfolioExportScripts
{
    private function script(string $slug): string
    {
        if ($slug === 'ivory-editorial') {
            return <<<'JS'
const root = document.querySelector(".ie-root");
const navWrap = document.querySelector("[data-nav-wrap]");
const nav = document.querySelector(".ie-nav");
const progress = document.querySelector(".ie-progress");
root?.classList.add("ie-js");
let lastY = 0;
let scheduled = false;
const updateScroll = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        const y = window.scrollY;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, y / max) : 0})`;
        nav?.classList.toggle("is-scrolled", y > 16);
        if (Math.abs(y - lastY) > 6) {
            navWrap?.classList.toggle("is-hidden", y > 160 && y > lastY);
            lastY = y;
        }
        root?.style.setProperty("--scroll-y", String(Math.min(y, 900)));
        scheduled = false;
    });
};
window.addEventListener("scroll", updateScroll, { passive: true });
updateScroll();
if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches && window.matchMedia("(pointer: fine)").matches) {
    let glowFrame = 0;
    window.addEventListener("pointermove", (event) => {
        if (glowFrame) return;
        glowFrame = requestAnimationFrame(() => {
            glowFrame = 0;
            root?.style.setProperty("--gx", `${event.clientX}px`);
            root?.style.setProperty("--gy", `${event.clientY}px`);
        });
    }, { passive: true });
}
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
if ("IntersectionObserver" in window) {
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-in");
            revealObserver.unobserve(entry.target);
        });
    }, { threshold: 0.14, rootMargin: "0px 0px -5% 0px" });
    if (reduceMotion) {
        document.querySelectorAll("[data-reveal]").forEach((element) => element.classList.add("is-in"));
    } else {
        document.querySelectorAll("[data-reveal]").forEach((element) => revealObserver.observe(element));
    }
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll("[data-nav-link]").forEach((link) => {
                link.dataset.active = String(link.getAttribute("data-nav-link") === entry.target.id);
            });
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    document.querySelectorAll(".ie-hero[id], .ie-about[id], .ie-journey[id], .ie-skills[id], .ie-projects[id], .ie-contact[id]").forEach((section) => sectionObserver.observe(section));
} else {
    document.querySelectorAll("[data-reveal]").forEach((element) => element.classList.add("is-in"));
}
document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener("click", (event) => {
        const id = link.getAttribute("href")?.slice(1);
        if (!id) return;
        const target = document.getElementById(id);
        if (!target) return;
        event.preventDefault();
        navWrap?.classList.remove("is-hidden");
        target.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
    });
});
document.querySelectorAll(".ie-magnet").forEach((button) => {
    button.addEventListener("mousemove", (event) => {
        if (reduceMotion) return;
        const bounds = button.getBoundingClientRect();
        button.style.transform = `translate(${(event.clientX - (bounds.left + bounds.width / 2)) * .2}px, ${(event.clientY - (bounds.top + bounds.height / 2)) * .28}px)`;
    });
    button.addEventListener("mouseleave", () => { button.style.transform = ""; });
});
const googleForm = document.querySelector("[data-google-form]");
if (googleForm) {
    const button = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = "Sending…";
        }
        if (status) status.hidden = true;
        window.setTimeout(() => {
            googleForm.reset();
            if (button) {
                button.disabled = false;
                button.textContent = button.dataset.originalText || "Send message";
            }
            if (status) status.hidden = false;
        }, 700);
    });
}
JS;
        }
        if ($slug === 'bento-grid') {
            return <<<'JS'
const root = document.querySelector(".rail");
const googleForm = document.querySelector("[data-google-form]");
const formFrame = document.querySelector('iframe[name="bg-gform-frame"]');
if (googleForm && formFrame) {
    let pendingSubmission = false;
    const submitButton = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        pendingSubmission = true;
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.firstChild.textContent = "Sending… ";
        }
        if (status) {
            status.textContent = "";
            status.classList.add("hidden");
        }
    });
    formFrame.addEventListener("load", () => {
        if (!pendingSubmission) return;
        pendingSubmission = false;
        googleForm.reset();
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.firstChild.textContent = "Send message ";
        }
        if (status) {
            status.textContent = "Your message was submitted.";
            status.classList.remove("hidden");
        }
    });
}
const progress = document.querySelector(".progress");
const updateProgress = () => {
    if (!progress) return;
    const height = document.documentElement.scrollHeight - window.innerHeight;
    progress.style.transform = `scaleX(${height > 0 ? Math.min(1, window.scrollY / height) : 0})`;
};
window.addEventListener("scroll", updateProgress, { passive: true });
updateProgress();

const sections = document.querySelectorAll("[data-section]");
if ("IntersectionObserver" in window) {
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll("[data-nav-link]").forEach((link) => {
                const active = link.getAttribute("data-nav-link") === entry.target.id;
                link.classList.toggle("active", active);
                if (link.closest("nav")) {
                    link.classList.toggle("mobile-active", active);
                }
            });
        });
    }, { rootMargin: "-40% 0px -55% 0px" });
    sections.forEach((section) => sectionObserver.observe(section));
}

if (root && !window.matchMedia("(prefers-reduced-motion: reduce)").matches && "IntersectionObserver" in window) {
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-visible");
            revealObserver.unobserve(entry.target);
        });
    }, { threshold: 0.35 });
    root.classList.add("motion-ready");
    root.querySelectorAll("[data-reveal]").forEach((element) => revealObserver.observe(element));
    requestAnimationFrame(() => root.classList.add("is-ready"));
}
JS;
        }
        if ($slug === 'cobalt-brutalist') {
            return <<<'JS'
const root = document.querySelector(".nb-root");
const progress = document.querySelector(".nb-progress");
const nav = document.querySelector(".nb-nav");
const updateScroll = () => {
    const max = document.documentElement.scrollHeight - window.innerHeight;
    if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
    if (nav) nav.classList.toggle("is-scrolled", window.scrollY > 16);
};
window.addEventListener("scroll", updateScroll, { passive: true });
updateScroll();

document.querySelectorAll("[data-scroll-link]").forEach((link) => {
    link.addEventListener("click", (event) => {
        const href = link.getAttribute("href");
        if (!href || !href.startsWith("#") || href.length < 2) return;
        const target = document.getElementById(href.slice(1));
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({
            behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
            block: "start",
        });
    });
});

if ("IntersectionObserver" in window) {
    const navObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll("[data-section-link]").forEach((link) => {
                link.dataset.active = String(link.getAttribute("data-section-link") === entry.target.id);
            });
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    document.querySelectorAll("[data-page-section]").forEach((section) => navObserver.observe(section));
}

if (root && !window.matchMedia("(prefers-reduced-motion: reduce)").matches && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add("is-in");
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });
    root.classList.add("nb-js");
    root.querySelectorAll("[data-reveal]").forEach((element) => observer.observe(element));
}

const googleForm = document.querySelector("[data-google-form]");
const formFrame = document.querySelector('iframe[name="nb-gform-frame"]');
if (googleForm && formFrame) {
    let pending = false;
    const button = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        pending = true;
        if (button) {
            button.disabled = true;
            button.firstChild.textContent = "Sending ";
        }
        if (status) {
            status.hidden = true;
            status.textContent = "";
        }
    });
    formFrame.addEventListener("load", () => {
        if (!pending) return;
        pending = false;
        googleForm.reset();
        if (button) {
            button.disabled = false;
            button.firstChild.textContent = "Send message ";
        }
        if (status) {
            status.textContent = "Your message was submitted.";
            status.hidden = false;
        }
    });
}
JS;
        }
        if ($slug === 'dark-orange-designer') {
            return <<<'JS'
const theme = document.querySelector(".theme");
const nav = document.querySelector(".nav");
const menuToggle = document.querySelector(".menu-toggle");
const mobileNavigation = document.querySelector("#mobile-navigation");
requestAnimationFrame(() => {
    theme?.classList.add("is-ready");
    nav?.classList.add("nav-in");
});
if (menuToggle && mobileNavigation) {
    menuToggle.addEventListener("click", () => {
        const open = menuToggle.getAttribute("aria-expanded") !== "true";
        menuToggle.setAttribute("aria-expanded", String(open));
        menuToggle.querySelector(".burger")?.classList.toggle("open", open);
        mobileNavigation.hidden = !open;
    });
    mobileNavigation.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => {
        menuToggle.setAttribute("aria-expanded", "false");
        menuToggle.querySelector(".burger")?.classList.remove("open");
        mobileNavigation.hidden = true;
    }));
}
const progress = document.querySelector(".progress-bar");
let scheduled = false;
const updateScroll = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
        scheduled = false;
    });
};
window.addEventListener("scroll", updateScroll, { passive: true });
updateScroll();

if ("IntersectionObserver" in window) {
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll("[data-section-link]").forEach((link) => {
                link.classList.toggle("active", link.getAttribute("data-section-link") === entry.target.id);
            });
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    document.querySelectorAll("[data-section]").forEach((section) => sectionObserver.observe(section));

    if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-in");
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.15, rootMargin: "0px 0px -8% 0px" });
        document.querySelectorAll(".reveal").forEach((element) => revealObserver.observe(element));
    } else {
        document.querySelectorAll(".reveal").forEach((element) => element.classList.add("is-in"));
    }
}

document.querySelectorAll("[data-journey-item] .jr-head").forEach((button) => {
    button.addEventListener("click", () => {
        const item = button.closest("[data-journey-item]");
        if (!item) return;
        const open = !item.classList.contains("open");
        item.classList.toggle("open", open);
        button.setAttribute("aria-expanded", String(open));
    });
});
document.querySelectorAll("[data-project-filter]").forEach((button) => {
    button.addEventListener("click", () => {
        const category = button.getAttribute("data-project-filter");
        document.querySelectorAll("[data-project-filter]").forEach((filter) => {
            const active = filter === button;
            filter.classList.toggle("active", active);
            filter.setAttribute("aria-selected", String(active));
        });
        document.querySelectorAll("[data-project-category]").forEach((project) => {
            project.hidden = category !== "all" && project.getAttribute("data-project-category") !== category;
        });
    });
});
const workList = document.querySelector("[data-work-list]");
const workPreview = document.querySelector(".work-preview");
if (workList && workPreview) {
    workList.addEventListener("pointermove", (event) => {
        workPreview.style.transform = `translate3d(${event.clientX + 28}px, ${event.clientY - 150}px, 0)`;
    });
    workList.addEventListener("pointerleave", () => workPreview.classList.remove("show"));
    workList.querySelectorAll("[data-project-index]").forEach((row) => {
        row.addEventListener("pointerenter", () => {
            const image = workPreview.querySelector(`[data-preview-index="${row.getAttribute("data-project-index")}"]`);
            workPreview.querySelectorAll("img").forEach((previewImage) => previewImage.classList.toggle("on", previewImage === image));
            workPreview.classList.toggle("show", !!image && !row.hidden);
        });
    });
}

const googleForm = document.querySelector("[data-google-form]");
const formFrame = document.querySelector('iframe[name="do-gform-frame"]');
if (googleForm && formFrame) {
    let pending = false;
    const button = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        pending = true;
        if (button) {
            button.disabled = true;
            button.firstChild.textContent = "Sending… ";
        }
    });
    formFrame.addEventListener("load", () => {
        if (!pending) return;
        pending = false;
        googleForm.reset();
        if (button) {
            button.disabled = false;
            button.firstChild.textContent = "Send message ";
        }
        if (status) {
            status.textContent = "Your message was submitted.";
            status.hidden = false;
        }
    });
}
JS;
        }
        if ($slug === 'emerald-minimalist') {
            return <<<'JS'
const nav = document.querySelector(".em-navbar");
const menuToggle = document.querySelector(".em-menu-toggle");
const mobileNav = document.querySelector("#em-mobile-nav");
const progress = document.querySelector(".em-progress-bar");
let scheduled = false;
const updateScroll = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
        nav?.classList.toggle("is-scrolled", window.scrollY > 24 || menuToggle?.getAttribute("aria-expanded") === "true");
        scheduled = false;
    });
};
window.addEventListener("scroll", updateScroll, { passive: true });
updateScroll();
if (menuToggle && mobileNav) {
    menuToggle.addEventListener("click", () => {
        const open = menuToggle.getAttribute("aria-expanded") !== "true";
        menuToggle.setAttribute("aria-expanded", String(open));
        mobileNav.hidden = !open;
        nav?.classList.toggle("is-scrolled", open || window.scrollY > 24);
    });
    mobileNav.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => {
        menuToggle.setAttribute("aria-expanded", "false");
        mobileNav.hidden = true;
        nav?.classList.toggle("is-scrolled", window.scrollY > 24);
    }));
}
if ("IntersectionObserver" in window) {
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            document.querySelectorAll(".em-nav-link").forEach((link) => {
                link.classList.toggle("active", link.getAttribute("href") === `#${entry.target.id}`);
            });
        });
    }, { rootMargin: "-45% 0px -50% 0px" });
    document.querySelectorAll(".em-section[id], .em-hero[id]").forEach((section) => sectionObserver.observe(section));
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const revealElements = document.querySelectorAll(".em-reveal");
    if (reduced) {
        revealElements.forEach((element) => element.classList.add("is-in"));
    } else {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-in");
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.15, rootMargin: "0px 0px -8% 0px" });
        revealElements.forEach((element) => revealObserver.observe(element));
    }
} else {
    document.querySelectorAll(".em-reveal").forEach((element) => element.classList.add("is-in"));
}
document.querySelectorAll(".em-filter").forEach((button) => {
    button.addEventListener("click", () => {
        const category = button.getAttribute("data-project-filter");
        document.querySelectorAll(".em-filter").forEach((filter) => filter.classList.toggle("active", filter === button));
        document.querySelectorAll(".em-project-card").forEach((project) => {
            project.hidden = category !== "All" && project.getAttribute("data-project-category") !== category;
        });
    });
});
document.querySelectorAll(".em-skill-card").forEach((card) => {
    const count = card.querySelector(".em-skill-count");
    if (!count) return;
    const target = Number(count.dataset.target);
    const render = (value) => { count.firstChild.textContent = String(value); };
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) {
        render(target);
    } else {
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const started = performance.now();
                const duration = 1900;
                const tick = (now) => {
                    const progress = Math.min(1, (now - started) / duration);
                    render(Math.round(target * (1 - Math.pow(1 - progress, 3))));
                    if (progress < 1) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
                counterObserver.unobserve(card);
            });
        }, { threshold: 0.2 });
        counterObserver.observe(card);
    }
});
const googleForm = document.querySelector("[data-google-form]");
const formFrame = document.querySelector('iframe[name="em-gform-frame"]');
if (googleForm && formFrame) {
    let pending = false;
    const button = googleForm.querySelector('button[type="submit"]');
    const status = googleForm.querySelector("[data-form-status]");
    googleForm.addEventListener("submit", () => {
        pending = true;
        if (button) {
            button.disabled = true;
            button.textContent = "Sending…";
        }
        if (status) {
            status.hidden = true;
            status.textContent = "";
        }
    });
    formFrame.addEventListener("load", () => {
        if (!pending) return;
        pending = false;
        googleForm.reset();
        if (button) {
            button.disabled = false;
            button.textContent = "Send message →";
        }
        if (status) {
            status.textContent = "Your message was submitted.";
            status.hidden = false;
        }
    });
}
JS;
        }        if ($slug === 'command-deck') {
            return <<<'JS'
const root = document.querySelector(".cd-root");
if (root) {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const scrollBehavior = () => (reduceMotion ? "auto" : "smooth");
    if ("IntersectionObserver" in window) root.classList.add("cd-js");

    /* ---------- Views (overview shows everything; a tab focuses one widget) ---------- */
    const sections = root.querySelectorAll("[data-view-section]");
    const targets = root.querySelectorAll("[data-view-target]");
    const viewIds = new Set(Array.from(targets, (el) => el.dataset.viewTarget));
    const labels = {};
    root.querySelectorAll("[data-view-label]").forEach((el) => { labels[el.dataset.viewTarget] = el.dataset.viewLabel; });
    const crumb = root.querySelector("[data-crumb]");
    const crumbSep = root.querySelector("[data-crumb-sep]");
    let view = "overview";

    const setView = (id) => {
        view = id;
        root.dataset.view = id;
        sections.forEach((el) => {
            const tokens = (el.dataset.viewSection || "").split(" ");
            el.hidden = !(id === "overview" || tokens.includes(id));
        });
        targets.forEach((el) => {
            const on = el.dataset.viewTarget === id;
            if (el.classList.contains("cd-rail-btn")) el.dataset.active = String(on);
            if (el.getAttribute("role") === "tab") el.setAttribute("aria-selected", String(on));
        });
        if (crumb) {
            crumb.hidden = id === "overview";
            crumb.textContent = labels[id] || "";
        }
        if (crumbSep) crumbSep.hidden = id === "overview";
        window.scrollTo({ top: 0, behavior: scrollBehavior() });
    };
    targets.forEach((el) => {
        el.addEventListener("click", (event) => {
            event.preventDefault();
            setView(el.dataset.viewTarget);
        });
    });
    document.querySelectorAll("[data-jump]").forEach((link) => {
        link.addEventListener("click", (event) => {
            const href = link.getAttribute("href") || "";
            if (!href.startsWith("#") || href.length < 2) return;
            event.preventDefault();
            const id = href.slice(1);
            if (!viewIds.has(id) && id !== "header") return;
            setView(id === "header" ? "overview" : id);
            const el = document.getElementById(id);
            if (el && view === "overview") el.scrollIntoView({ behavior: scrollBehavior(), block: "start" });
        });
    });

    /* ---------- Scroll progress + percentage readout ---------- */
    const progress = root.querySelector("[data-progress]");
    const topInner = root.querySelector(".cd-top-inner");
    const pct = root.querySelector("[data-scroll-pct]");
    let scheduled = false;
    const updateScroll = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            const max = document.documentElement.scrollHeight - window.innerHeight;
            const p = max > 0 ? Math.min(1, window.scrollY / max) : 0;
            if (progress) progress.style.transform = `scaleX(${p})`;
            if (pct) pct.textContent = `${String(Math.round(p * 100)).padStart(3, "0")}%`;
            topInner?.classList.toggle("is-scrolled", window.scrollY > 8);
        });
    };
    window.addEventListener("scroll", updateScroll, { passive: true });
    updateScroll();

    /* ---------- Scroll reveal ---------- */
    const reveals = root.querySelectorAll("[data-reveal]");
    if ("IntersectionObserver" in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-in");
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.1, rootMargin: "0px 0px -5% 0px" });
        reveals.forEach((el) => observer.observe(el));
    } else {
        reveals.forEach((el) => el.classList.add("is-in"));
    }

    /* ---------- Project tag filters ---------- */
    const grid = root.querySelector("[data-project-grid]");
    const items = root.querySelectorAll("[data-project-item]");
    const filterButtons = root.querySelectorAll(".cd-filter[data-project-filter]");
    const countEl = root.querySelector("[data-project-count]");
    let activeFilter = "";
    const applyFilter = (tag) => {
        activeFilter = tag;
        let shown = 0;
        items.forEach((item) => {
            let tags = [];
            try { tags = JSON.parse(item.dataset.tags || "[]"); } catch (error) { tags = []; }
            const visible = tag === "" || tags.includes(tag);
            item.hidden = !visible;
            if (visible) shown += 1;
        });
        grid?.classList.toggle("is-filtered", tag !== "");
        filterButtons.forEach((button) => button.setAttribute("aria-selected", String(button.dataset.projectFilter === tag)));
        if (countEl) countEl.textContent = `${String(shown).padStart(2, "0")}/${countEl.dataset.total}`;
    };
    filterButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const tag = button.dataset.projectFilter || "";
            applyFilter(tag !== "" && activeFilter === tag ? "" : tag);
        });
    });
    root.querySelectorAll("[data-project-tag]").forEach((pill) => {
        pill.addEventListener("click", () => applyFilter(pill.dataset.projectTag || ""));
    });

    /* ---------- Google Form submission state ---------- */
    const googleForm = root.querySelector("[data-google-form]");
    const formFrame = root.querySelector('iframe[name="cd-gform-frame"]');
    if (googleForm && formFrame) {
        const button = googleForm.querySelector('button[type="submit"]');
        const label = googleForm.querySelector("[data-submit-label]");
        const status = googleForm.querySelector("[data-form-status]");
        let pending = false;
        let fallbackTimer = 0;
        const finish = () => {
            if (!pending) return;
            pending = false;
            window.clearTimeout(fallbackTimer);
            googleForm.reset();
            if (button) button.disabled = false;
            if (label) label.textContent = "Send";
            if (status) status.hidden = false;
        };
        googleForm.addEventListener("submit", () => {
            pending = true;
            if (button) button.disabled = true;
            if (label) label.textContent = "…";
            if (status) status.hidden = true;
            // Google's response can't be read cross-origin; confirm optimistically if the frame never reports.
            fallbackTimer = window.setTimeout(finish, 4000);
        });
        formFrame.addEventListener("load", finish);
    }
}
JS;
        }         if ($slug === 'nebula-glass') {
            return <<<'JS'
const root = document.querySelector(".ng-root");
if (root) {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const hasObserver = "IntersectionObserver" in window;
    if (hasObserver) root.classList.add("ng-js");

    /* ---------- Scroll progress + navbar state ---------- */
    const progress = root.querySelector("[data-progress]");
    const nav = root.querySelector("[data-nav]");
    let scheduled = false;
    const updateScroll = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            const max = document.documentElement.scrollHeight - window.innerHeight;
            if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
            nav?.classList.toggle("is-scrolled", window.scrollY > 16);
        });
    };
    window.addEventListener("scroll", updateScroll, { passive: true });
    updateScroll();

    /* ---------- Smooth in-page links ---------- */
    root.querySelectorAll("[data-scroll-link]").forEach((link) => {
        link.addEventListener("click", (event) => {
            const href = link.getAttribute("href") || "";
            if (!href.startsWith("#") || href.length < 2) return;
            const target = document.getElementById(href.slice(1));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
        });
    });

    /* ---------- Active nav link ---------- */
    if (hasObserver) {
        const navLinks = root.querySelectorAll("[data-section-link]");
        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                navLinks.forEach((link) => {
                    link.dataset.active = String(link.getAttribute("data-section-link") === entry.target.id);
                });
            });
        }, { rootMargin: "-45% 0px -50% 0px" });
        root.querySelectorAll("[data-page-section]").forEach((section) => sectionObserver.observe(section));

        /* ---------- Scroll reveal ---------- */
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-in");
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });
        root.querySelectorAll("[data-reveal]").forEach((element) => revealObserver.observe(element));
    }

    /* ---------- Google Form submission state ---------- */
    const googleForm = root.querySelector("[data-google-form]");
    const formFrame = root.querySelector('iframe[name="ng-gform-frame"]');
    if (googleForm && formFrame) {
        const button = googleForm.querySelector('button[type="submit"]');
        const label = googleForm.querySelector("[data-submit-label]");
        const status = googleForm.querySelector("[data-form-status]");
        let pending = false;
        let fallbackTimer = 0;
        const finish = () => {
            if (!pending) return;
            pending = false;
            window.clearTimeout(fallbackTimer);
            googleForm.reset();
            if (button) button.disabled = false;
            if (label) label.textContent = "Send";
            if (status) status.hidden = false;
        };
        googleForm.addEventListener("submit", () => {
            pending = true;
            if (button) button.disabled = true;
            if (label) label.textContent = "…";
            if (status) status.hidden = true;
            // Google's response can't be read cross-origin; confirm optimistically if the frame never reports.
            fallbackTimer = window.setTimeout(finish, 4000);
        });
        formFrame.addEventListener("load", finish);
    }
}
JS;
        }         if ($slug === 'newsprint') {
            return <<<'JS'
const root = document.querySelector(".np-root");
if (root) {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const hasObserver = "IntersectionObserver" in window;
    // Hero/reveal styles only apply once this class exists, so no-JS output stays visible.
    root.classList.add("np-js");

    /* ---------- Scroll progress + masthead state ---------- */
    const progress = root.querySelector("[data-progress]");
    const nav = root.querySelector("[data-nav]");
    let scheduled = false;
    const updateScroll = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            const max = document.documentElement.scrollHeight - window.innerHeight;
            if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, window.scrollY / max) : 0})`;
            nav?.classList.toggle("is-scrolled", window.scrollY > 16);
        });
    };
    window.addEventListener("scroll", updateScroll, { passive: true });
    updateScroll();

    /* ---------- Smooth in-page links ---------- */
    root.querySelectorAll("[data-scroll-link]").forEach((link) => {
        link.addEventListener("click", (event) => {
            const href = link.getAttribute("href") || "";
            if (!href.startsWith("#") || href.length < 2) return;
            const target = document.getElementById(href.slice(1));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
        });
    });

    const reveals = root.querySelectorAll("[data-reveal]");
    if (hasObserver) {
        /* ---------- Active nav link ---------- */
        const navLinks = root.querySelectorAll("[data-section-link]");
        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                navLinks.forEach((link) => {
                    link.dataset.active = String(link.getAttribute("data-section-link") === entry.target.id);
                });
            });
        }, { rootMargin: "-45% 0px -50% 0px" });
        root.querySelectorAll("[data-page-section]").forEach((section) => sectionObserver.observe(section));

        /* ---------- Scroll reveal ---------- */
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-in");
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.1, rootMargin: "0px 0px -5% 0px" });
        reveals.forEach((element) => revealObserver.observe(element));
    } else {
        reveals.forEach((element) => element.classList.add("is-in"));
    }

    /* ---------- Google Form submission state ---------- */
    const googleForm = root.querySelector("[data-google-form]");
    const formFrame = root.querySelector('iframe[name="np-gform-frame"]');
    if (googleForm && formFrame) {
        const button = googleForm.querySelector('button[type="submit"]');
        const label = googleForm.querySelector("[data-submit-label]");
        const status = googleForm.querySelector("[data-form-status]");
        let pending = false;
        let fallbackTimer = 0;
        const finish = () => {
            if (!pending) return;
            pending = false;
            window.clearTimeout(fallbackTimer);
            googleForm.reset();
            if (button) button.disabled = false;
            if (label) label.textContent = "Send message";
            if (status) status.hidden = false;
        };
        googleForm.addEventListener("submit", () => {
            pending = true;
            if (button) button.disabled = true;
            if (label) label.textContent = "Sending…";
            if (status) status.hidden = true;
            // Google's response can't be read cross-origin; confirm optimistically if the frame never reports.
            fallbackTimer = window.setTimeout(finish, 4000);
        });
        formFrame.addEventListener("load", finish);
    }
}
JS;
        }         if ($slug === 'paper-stack') {
            return <<<'JS'
const root = document.querySelector(".ps-root");
if (root) {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const hasObserver = "IntersectionObserver" in window;
    // Hero/reveal styles only apply once this class exists, so no-JS output stays visible.
    root.classList.add("ps-js");

    /* ---------- Scroll progress, navbar state and sheet parallax ---------- */
    const progress = root.querySelector("[data-progress]");
    const nav = root.querySelector("[data-nav]");
    let scheduled = false;
    const updateScroll = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            const y = window.scrollY;
            const max = document.documentElement.scrollHeight - window.innerHeight;
            if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(1, y / max) : 0})`;
            nav?.classList.toggle("is-scrolled", y > 16);
            root.style.setProperty("--sy", String(Math.min(y, 900)));
        });
    };
    window.addEventListener("scroll", updateScroll, { passive: true });
    updateScroll();

    /* ---------- Smooth in-page links ---------- */
    root.querySelectorAll("[data-scroll-link]").forEach((link) => {
        link.addEventListener("click", (event) => {
            const href = link.getAttribute("href") || "";
            if (!href.startsWith("#") || href.length < 2) return;
            const target = document.getElementById(href.slice(1));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
        });
    });

    const reveals = root.querySelectorAll("[data-reveal]");
    if (hasObserver) {
        /* ---------- Active nav link ---------- */
        const navLinks = root.querySelectorAll("[data-section-link]");
        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                navLinks.forEach((link) => {
                    link.dataset.active = String(link.getAttribute("data-section-link") === entry.target.id);
                });
            });
        }, { rootMargin: "-45% 0px -50% 0px" });
        root.querySelectorAll("[data-page-section]").forEach((section) => sectionObserver.observe(section));

        /* ---------- Scroll reveal ---------- */
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-in");
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.1, rootMargin: "0px 0px -6% 0px" });
        reveals.forEach((element) => revealObserver.observe(element));
    } else {
        reveals.forEach((element) => element.classList.add("is-in"));
    }

    /* ---------- Google Form submission state ---------- */
    const googleForm = root.querySelector("[data-google-form]");
    const formFrame = root.querySelector('iframe[name="ps-gform-frame"]');
    if (googleForm && formFrame) {
        const button = googleForm.querySelector('button[type="submit"]');
        const label = googleForm.querySelector("[data-submit-label]");
        const status = googleForm.querySelector("[data-form-status]");
        let pending = false;
        let fallbackTimer = 0;
        const finish = () => {
            if (!pending) return;
            pending = false;
            window.clearTimeout(fallbackTimer);
            googleForm.reset();
            if (button) button.disabled = false;
            if (label) label.textContent = "Send";
            if (status) status.hidden = false;
        };
        googleForm.addEventListener("submit", () => {
            pending = true;
            if (button) button.disabled = true;
            if (label) label.textContent = "…";
            if (status) status.hidden = true;
            // Google's response can't be read cross-origin; confirm optimistically if the frame never reports.
            fallbackTimer = window.setTimeout(finish, 4000);
        });
        formFrame.addEventListener("load", finish);
    }
}
JS;
        }

        return <<<'JS'
const menuToggle = document.querySelector(".menu-toggle");
const siteLinks = document.querySelector("#site-links");
if (menuToggle && siteLinks) {
    menuToggle.addEventListener("click", () => {
        const open = siteLinks.classList.toggle("is-open");
        menuToggle.setAttribute("aria-expanded", String(open));
    });
    siteLinks.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => {
        siteLinks.classList.remove("is-open");
        menuToggle.setAttribute("aria-expanded", "false");
    }));
}
document.querySelectorAll("[data-filter]").forEach((button) => {
    button.addEventListener("click", () => {
        const category = button.dataset.filter;
        document.querySelectorAll("[data-filter]").forEach((item) => item.classList.toggle("active", item === button));
        document.querySelectorAll(".project-card").forEach((card) => {
            card.hidden = category !== "all" && card.dataset.category !== category;
        });
    });
});
JS;
    }
}
