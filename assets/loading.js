(() => {
  const DEFAULT_LABEL = "Carregando...";
  const SUBMIT_LABEL = "Processando...";
  const SHOW_DELAY_MS = 120;
  const TOUCH_SHOW_DELAY_MS = 70;
  const HIDE_DELAY_MS = 180;
  const MAX_VISIBLE_MS = 30000;
  const PUBLIC_ROUTE_SLUGS = new Set(["home", "privacidade", "termos", "cookies", "dados", "vendas"]);

  let overlay = null;
  let labelNode = null;
  let showTimer = 0;
  let hideTimer = 0;
  let failSafeTimer = 0;
  let isVisible = false;
  let pendingInteraction = null;
  const activeTokens = new Set();

  const ensureOverlay = () => {
    if (overlay && overlay.isConnected) {
      return overlay;
    }

    if (!document.body) {
      return null;
    }

    const existingOverlay = document.querySelector("[data-app-loading-overlay]");
    if (existingOverlay instanceof HTMLElement) {
      overlay = existingOverlay;
      labelNode = overlay.querySelector("[data-app-loading-label]");
      return overlay;
    }

    overlay = document.createElement("div");
    overlay.className = "app-loading-overlay";
    overlay.setAttribute("data-app-loading-overlay", "");
    overlay.setAttribute("aria-hidden", "true");
    overlay.hidden = true;

    const panel = document.createElement("div");
    panel.className = "app-loading-panel";
    panel.setAttribute("role", "status");
    panel.setAttribute("aria-live", "polite");

    const spinner = document.createElement("span");
    spinner.className = "app-loading-spinner";
    spinner.setAttribute("aria-hidden", "true");

    labelNode = document.createElement("span");
    labelNode.className = "app-loading-copy";
    labelNode.setAttribute("data-app-loading-label", "");
    labelNode.textContent = DEFAULT_LABEL;

    panel.append(spinner, labelNode);
    overlay.append(panel);
    document.body.append(overlay);

    return overlay;
  };

  const setLabel = (label) => {
    const nextLabel = String(label || "").trim() || DEFAULT_LABEL;
    const root = ensureOverlay();
    if (!root || !labelNode) return;
    labelNode.textContent = nextLabel;
  };

  const reveal = () => {
    const root = ensureOverlay();
    if (!root) return;

    root.hidden = false;
    root.setAttribute("aria-hidden", "false");
    document.body.classList.add("is-app-loading");

    if (activeTokens.size > 0) {
      root.classList.add("is-visible");
    }
  };

  const conceal = () => {
    if (!overlay) return;

    overlay.classList.remove("is-visible");
    overlay.setAttribute("aria-hidden", "true");
    document.body?.classList.remove("is-app-loading");

    window.setTimeout(() => {
      if (overlay && activeTokens.size === 0) {
        overlay.hidden = true;
      }
    }, HIDE_DELAY_MS);
  };

  const hideToken = (token) => {
    if (token && activeTokens.has(token)) {
      activeTokens.delete(token);
    } else if (!token) {
      activeTokens.clear();
    }

    if (activeTokens.size > 0) return;

    if (failSafeTimer) {
      window.clearTimeout(failSafeTimer);
      failSafeTimer = 0;
    }

    if (showTimer) {
      window.clearTimeout(showTimer);
      showTimer = 0;
    }

    if (!isVisible) return;

    if (hideTimer) {
      window.clearTimeout(hideTimer);
    }

    hideTimer = window.setTimeout(() => {
      hideTimer = 0;
      if (activeTokens.size > 0) return;
      isVisible = false;
      conceal();
    }, HIDE_DELAY_MS);
  };

  const show = (options = {}) => {
    const token = {};
    activeTokens.add(token);
    setLabel(options.label || DEFAULT_LABEL);

    if (!failSafeTimer) {
      failSafeTimer = window.setTimeout(() => {
        failSafeTimer = 0;
        hideToken();
      }, MAX_VISIBLE_MS);
    }

    if (hideTimer) {
      window.clearTimeout(hideTimer);
      hideTimer = 0;
    }

    if (!isVisible && !showTimer) {
      const delay = Number.isFinite(options.delay)
        ? Math.max(0, options.delay)
        : SHOW_DELAY_MS;

      if (delay === 0) {
        isVisible = true;
        reveal();
      } else {
        showTimer = window.setTimeout(() => {
          showTimer = 0;
          if (activeTokens.size <= 0) return;
          isVisible = true;
          reveal();
        }, delay);
      }
    }

    let isDone = false;
    return {
      done() {
        if (isDone) return;
        isDone = true;
        hideToken(token);
      },
      hide() {
        this.done();
      },
    };
  };

  const withLoading = (work, options = {}) => {
    const token = show(options);
    return Promise.resolve()
      .then(work)
      .finally(() => {
        token.done();
      });
  };

  const reset = () => {
    if (pendingInteraction?.clearTimer) {
      window.clearTimeout(pendingInteraction.clearTimer);
    }
    pendingInteraction = null;
    activeTokens.clear();
    if (showTimer) {
      window.clearTimeout(showTimer);
      showTimer = 0;
    }
    if (hideTimer) {
      window.clearTimeout(hideTimer);
      hideTimer = 0;
    }
    if (failSafeTimer) {
      window.clearTimeout(failSafeTimer);
      failSafeTimer = 0;
    }
    isVisible = false;
    if (overlay) {
      overlay.classList.remove("is-visible");
      overlay.hidden = true;
      overlay.setAttribute("aria-hidden", "true");
    }
    document.body?.classList.remove("is-app-loading");
  };

  const routeSlugFromPathname = (pathname) => {
    const normalizedPath = String(pathname || "").replace(/\/+$/, "");
    if (!normalizedPath) return "";

    const segments = normalizedPath.split("/").filter(Boolean);
    const lastSegment = segments[segments.length - 1] || "";
    return lastSegment.replace(/\.php$/i, "").toLowerCase();
  };

  const isPublicRouteDestination = (pathname) => {
    const slug = routeSlugFromPathname(pathname);
    return slug === "" || PUBLIC_ROUTE_SLUGS.has(slug);
  };

  const shouldIgnoreAnchor = (anchor) => {
    if (!(anchor instanceof HTMLAnchorElement)) return true;
    if (anchor.hasAttribute("download")) return true;
    if (anchor.closest("[data-no-loading]")) return true;

    const target = String(anchor.getAttribute("target") || "").trim().toLowerCase();
    if (target && target !== "_self") return true;

    const rawHref = String(anchor.getAttribute("href") || "").trim();
    if (!rawHref) return true;

    const lowerHref = rawHref.toLowerCase();
    if (
      lowerHref.startsWith("#") ||
      lowerHref.startsWith("javascript:") ||
      lowerHref.startsWith("mailto:") ||
      lowerHref.startsWith("tel:")
    ) {
      return true;
    }

    try {
      const nextUrl = new URL(anchor.href, window.location.href);
      const currentUrl = new URL(window.location.href);
      const sameDocument =
        nextUrl.origin === currentUrl.origin &&
        nextUrl.pathname === currentUrl.pathname &&
        nextUrl.search === currentUrl.search &&
        nextUrl.hash !== currentUrl.hash;

      if (sameDocument) return true;
      if (nextUrl.href === currentUrl.href) return true;
      if (isPublicRouteDestination(nextUrl.pathname)) return true;
    } catch (_error) {
      return true;
    }

    return false;
  };

  const clearPendingInteraction = () => {
    if (!pendingInteraction) return;
    if (pendingInteraction.clearTimer) {
      window.clearTimeout(pendingInteraction.clearTimer);
    }
    pendingInteraction.token.done();
    pendingInteraction = null;
  };

  const startPendingInteraction = (kind, element, label) => {
    clearPendingInteraction();
    pendingInteraction = {
      kind,
      element,
      confirmed: false,
      clearTimer: 0,
      token: show({ label, delay: TOUCH_SHOW_DELAY_MS }),
    };
  };

  const confirmPendingInteraction = (kind, element, label) => {
    if (
      pendingInteraction &&
      pendingInteraction.kind === kind &&
      pendingInteraction.element === element
    ) {
      pendingInteraction.confirmed = true;
      if (pendingInteraction.clearTimer) {
        window.clearTimeout(pendingInteraction.clearTimer);
        pendingInteraction.clearTimer = 0;
      }
      return;
    }

    clearPendingInteraction();
    pendingInteraction = {
      kind,
      element,
      confirmed: true,
      clearTimer: 0,
      token: show({ label, delay: 0 }),
    };
  };

  const scheduleUnconfirmedInteractionCleanup = () => {
    if (!pendingInteraction || pendingInteraction.confirmed) return;
    pendingInteraction.clearTimer = window.setTimeout(() => {
      if (pendingInteraction && !pendingInteraction.confirmed) {
        clearPendingInteraction();
      }
    }, 700);
  };

  const eligibleForm = (form) => {
    if (!(form instanceof HTMLFormElement) || form.hasAttribute("data-no-loading")) {
      return false;
    }

    const method = String(form.getAttribute("method") || "get").trim().toLowerCase();
    const target = String(form.getAttribute("target") || "").trim().toLowerCase();
    return method !== "dialog" && (!target || target === "_self");
  };

  const bindPageLoadingFeedback = () => {
    document.addEventListener(
      "pointerdown",
      (event) => {
        if (event.button !== 0 || !event.isPrimary) return;
        const target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        const anchor = target.closest("a[href]");
        if (anchor instanceof HTMLAnchorElement && !shouldIgnoreAnchor(anchor)) {
          startPendingInteraction(
            "anchor",
            anchor,
            anchor.getAttribute("data-loading-label") || DEFAULT_LABEL
          );
          return;
        }

        const submitControl = target.closest(
          'button:not([type]), button[type="submit"], input[type="submit"], input[type="image"]'
        );
        const form = submitControl?.form;
        if (eligibleForm(form)) {
          startPendingInteraction(
            "form",
            form,
            form.getAttribute("data-loading-label") || SUBMIT_LABEL
          );
        }
      },
      { capture: true, passive: true }
    );

    document.addEventListener("pointercancel", clearPendingInteraction, true);
    document.addEventListener("pointerup", scheduleUnconfirmedInteractionCleanup, true);

    document.addEventListener(
      "click",
      (event) => {
        if (event.button !== 0) return;

        const target =
          event.target instanceof Element ? event.target : event.target?.parentElement;
        const anchor = target?.closest("a[href]");
        if (!anchor) return;

        window.setTimeout(() => {
          if (
            event.defaultPrevented ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            shouldIgnoreAnchor(anchor)
          ) {
            clearPendingInteraction();
            return;
          }
          confirmPendingInteraction(
            "anchor",
            anchor,
            anchor.getAttribute("data-loading-label") || DEFAULT_LABEL
          );
        }, 0);
      },
      true
    );

    document.addEventListener(
      "submit",
      (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form) return;

        window.setTimeout(() => {
          if (event.defaultPrevented || !eligibleForm(form)) {
            clearPendingInteraction();
            return;
          }
          confirmPendingInteraction(
            "form",
            form,
            form.getAttribute("data-loading-label") || SUBMIT_LABEL
          );
        }, 0);
      },
      true
    );
  };

  window.BexonLoading = {
    show,
    hide: () => hideToken(),
    reset,
    setLabel,
    withLoading,
  };

  ensureOverlay();
  bindPageLoadingFeedback();
  window.addEventListener("pageshow", reset);
})();
