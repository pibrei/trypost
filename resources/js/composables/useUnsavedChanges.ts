import { router } from '@inertiajs/vue3';
import { onScopeDispose, ref, watch } from 'vue';

/**
 * Asks before unsaved work is lost: closing what holds it (through `guard`),
 * an Inertia visit to another page, the browser's back button, or reloading
 * and closing the tab (the browser's own prompt, which no page can word).
 * Writes (POST, PUT, ...) and partial reloads of the current page are never
 * held.
 *
 * The back button cannot be cancelled, so while there is unsaved work the
 * current history entry is doubled: back lands on the copy (an entry without
 * Inertia state, which Inertia leaves on the same page) and asks from there.
 */
export const useUnsavedChanges = (isDirty: () => boolean) => {
    const confirming = ref(false);
    let pending: (() => void) | null = null;
    let leaving = false;

    const leave = (action: () => void): void => {
        leaving = true;
        try {
            action();
        } finally {
            leaving = false;
        }
    };

    const guard = (action: () => void): void => {
        if (leaving || !isDirty()) {
            action();
            return;
        }
        pending = action;
        confirming.value = true;
    };

    let holdingBack = false;

    const holdBack = (): void => {
        if (holdingBack) return;
        holdingBack = true;
        const state = window.history.state;
        window.history.replaceState(null, '', window.location.href);
        window.history.pushState(state, '', window.location.href);
    };

    const releaseBack = (): void => {
        if (!holdingBack) return;
        holdingBack = false;
        window.history.back();
    };

    const discard = (): void => {
        const action = pending;
        pending = null;
        confirming.value = false;
        if (action) leave(action);
    };

    const keepEditing = (): void => {
        pending = null;
        confirming.value = false;
        if (isDirty()) holdBack();
    };

    const onPopState = (): void => {
        if (!holdingBack || leaving) return;
        holdingBack = false;
        guard(() => window.history.back());
    };

    watch(isDirty, (dirty) => (dirty ? holdBack() : releaseBack()));

    const stopVisits = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (
            leaving ||
            visit.method !== 'get' ||
            visit.only.length > 0 ||
            visit.except.length > 0 ||
            visit.prefetch ||
            visit.url.href === window.location.href ||
            !isDirty()
        ) {
            return;
        }

        event.preventDefault();
        guard(() =>
            router.visit(visit.url.href, {
                preserveScroll: visit.preserveScroll,
                preserveState: visit.preserveState,
                replace: visit.replace,
            }),
        );
    });

    const onBeforeUnload = (event: BeforeUnloadEvent): void => {
        if (leaving || !isDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    };

    window.addEventListener('beforeunload', onBeforeUnload);
    window.addEventListener('popstate', onPopState);

    onScopeDispose(() => {
        stopVisits();
        window.removeEventListener('beforeunload', onBeforeUnload);
        window.removeEventListener('popstate', onPopState);
    });

    return { confirming, guard, discard, keepEditing, leave };
};
