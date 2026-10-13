import { nextTick, ref, watch } from 'vue';

import { useUnsavedChanges } from '@/composables/useUnsavedChanges';

interface ComposerUnsavedChangesOptions {
    /** Only an edit of a saved post can lose work: a new post is autosaved. */
    editingSavedPost: boolean;
    isOpen: () => boolean;
    isSubmitting: () => boolean;
    /** Whether everything the snapshot reads has loaded (the post's channels). */
    isReady: () => boolean;
    /** What saving would send, as a string to compare. */
    snapshot: () => string;
}

/**
 * The composer's unsaved-changes guard: compares what saving would send with
 * what it would have sent when the post opened.
 *
 * Settings fill some values in on their own once their data loads (a renewed
 * music cover, the TikTok creator's limits), so the opened state is taken
 * again at the person's first interaction and nothing counts before it.
 */
export const useComposerUnsavedChanges = (
    options: ComposerUnsavedChangesOptions,
) => {
    const opened = ref<string | null>(null);
    const interacted = ref(false);
    const saved = ref(false);

    watch(
        options.isReady,
        async (ready) => {
            if (!ready || opened.value !== null) return;
            await nextTick();
            opened.value = options.snapshot();
        },
        { immediate: true },
    );

    const markInteracted = (): void => {
        if (interacted.value) return;
        interacted.value = true;
        if (opened.value !== null) {
            opened.value = options.snapshot();
        }
    };

    const markSaved = (): void => {
        saved.value = true;
    };

    const hasUnsavedChanges = (): boolean =>
        options.editingSavedPost &&
        options.isOpen() &&
        interacted.value &&
        !saved.value &&
        !options.isSubmitting() &&
        opened.value !== null &&
        options.snapshot() !== opened.value;

    return {
        ...useUnsavedChanges(hasUnsavedChanges),
        markInteracted,
        markSaved,
    };
};
