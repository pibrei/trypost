<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ confirm: [] }>();

const confirm = (): void => {
    emit('confirm');
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent
            class="gap-0 px-0 pt-6 pb-0 sm:max-w-[512px]"
            data-testid="composer-back-confirm"
        >
            <AlertDialogHeader class="gap-3 px-6 pb-4 text-left">
                <AlertDialogTitle class="font-sans text-base font-medium">
                    {{ $t('posts.composer.back_confirm.title') }}
                </AlertDialogTitle>
                <AlertDialogDescription class="text-sm text-foreground">
                    {{ $t('posts.composer.back_confirm.description') }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter class="mx-2 mb-2">
                <AlertDialogCancel data-testid="composer-back-cancel">
                    {{ $t('common.cancel') }}
                </AlertDialogCancel>
                <Button
                    variant="destructive"
                    size="lg"
                    data-testid="composer-back-confirm-go"
                    @click="confirm"
                >
                    {{ $t('posts.composer.back_confirm.confirm') }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
