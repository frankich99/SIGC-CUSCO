import { ref } from 'vue';

// Estado reactivo singleton global para los modales de autenticación
const isLoginModalOpen = ref(false);
const isRegisterModalOpen = ref(false);

export function useAuthModal() {
    function openLogin() {
        isRegisterModalOpen.value = false;
        isLoginModalOpen.value = true;
    }

    function openRegister() {
        isLoginModalOpen.value = false;
        isRegisterModalOpen.value = true;
    }

    function switchToRegister() {
        isLoginModalOpen.value = false;
        isRegisterModalOpen.value = true;
    }

    function switchToLogin() {
        isRegisterModalOpen.value = false;
        isLoginModalOpen.value = true;
    }

    function closeAuthModals() {
        isLoginModalOpen.value = false;
        isRegisterModalOpen.value = false;
    }

    return {
        isLoginModalOpen,
        isRegisterModalOpen,
        openLogin,
        openRegister,
        switchToRegister,
        switchToLogin,
        closeAuthModals,
    };
}

// Inicializar escuchas globales en el navegador
if (typeof window !== 'undefined') {
    // Exponer helpers en window para disparadores globales
    (window as any).openLoginModal = () => {
        isRegisterModalOpen.value = false;
        isLoginModalOpen.value = true;
    };

    (window as any).openRegisterModal = () => {
        isLoginModalOpen.value = false;
        isRegisterModalOpen.value = true;
    };

    // Revisar hash o query params al cargar la página
    function checkUrlAuthIntent() {
        try {
            const hash = window.location.hash.toLowerCase();
            const search = new URLSearchParams(window.location.search);
            const authParam = search.get('auth') || search.get('modal');

            if (
                hash === '#login' ||
                authParam === 'login' ||
                search.has('login')
            ) {
                isRegisterModalOpen.value = false;
                isLoginModalOpen.value = true;
            } else if (
                hash === '#register' ||
                authParam === 'register' ||
                search.has('register')
            ) {
                isLoginModalOpen.value = false;
                isRegisterModalOpen.value = true;
            }
        } catch {
            // Silencioso
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkUrlAuthIntent, {
            once: true,
        });
    } else {
        setTimeout(checkUrlAuthIntent, 50);
    }

    window.addEventListener('hashchange', checkUrlAuthIntent);

    // Interceptor global para enlaces hacia /login y /register en el portal público
    document.addEventListener(
        'click',
        (event: MouseEvent) => {
            // Ignorar clics con modificadores (Ctrl, Cmd, Shift, etc.) o clic derecho/central
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.ctrlKey ||
                event.metaKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            const target = (event.target as HTMLElement)?.closest('a');
            if (!target) return;

            const href = target.getAttribute('href');
            if (!href) return;

            // Si el enlace apunta exactamente a /login
            if (
                href === '/login' ||
                href === 'login' ||
                href.endsWith('/login')
            ) {
                // Si la URL actual ya es /login, permitir comportamiento normal
                if (window.location.pathname === '/login') return;

                event.preventDefault();
                event.stopPropagation();
                isRegisterModalOpen.value = false;
                isLoginModalOpen.value = true;
            }
            // Si el enlace apunta exactamente a /register
            else if (
                href === '/register' ||
                href === 'register' ||
                href.endsWith('/register')
            ) {
                // Si la URL actual ya es /register, permitir comportamiento normal
                if (window.location.pathname === '/register') return;

                event.preventDefault();
                event.stopPropagation();
                isLoginModalOpen.value = false;
                isRegisterModalOpen.value = true;
            }
        },
        true, // Capture phase para interceptar antes de la navegación de Inertia
    );
}
