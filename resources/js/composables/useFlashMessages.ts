import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

export function useFlashMessages() {
    const page = usePage();

    const flash = computed<FlashMessages>(() => {
        return (page.props.flash as FlashMessages) || {};
    });

    const showFlash = () => {
        if (flash.value.success) {
            toast.success(flash.value.success);
        }
        if (flash.value.error) {
            toast.error(flash.value.error);
        }
        if (flash.value.warning) {
            toast.warning(flash.value.warning);
        }
        if (flash.value.info) {
            toast.info(flash.value.info);
        }
    };

    return {
        flash,
        showFlash,
        toast,
    };
}
