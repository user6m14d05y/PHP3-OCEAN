import { ref, nextTick } from 'vue';
import { Toast } from 'bootstrap';

/**
 * Composable thay thế SweetAlert2 bằng Bootstrap Toast.
 * Sử dụng: const { toast, showToast } = useToast('uniqueToastId');
 * Trong template: cần thêm Toast HTML component (xem bên dưới).
 */
export function useToast(toastId = 'appToast') {
  const toast = ref({ message: '', type: 'success' });

  const showToast = (message, type = 'success') => {
    toast.value = { message, type };
    nextTick(() => {
      const el = document.getElementById(toastId);
      if (el) Toast.getOrCreateInstance(el, { delay: 3000 }).show();
    });
  };

  return { toast, showToast };
}
