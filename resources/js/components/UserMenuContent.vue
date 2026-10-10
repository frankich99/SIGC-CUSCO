<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings, ShieldCheck } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem v-if="user.role === 'admin'" :as-child="true">
            <Link
                class="flex w-full cursor-pointer items-center rounded-xl font-bold text-rose-950 dark:text-rose-200"
                href="/users"
            >
                <ShieldCheck
                    class="mr-2 inline h-4 w-4 text-emerald-600 dark:text-emerald-400"
                />
                Usuarios y Roles
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="flex w-full cursor-pointer items-center rounded-xl"
                :href="edit()"
                prefetch
            >
                <Settings
                    class="mr-2 h-4 w-4 text-slate-600 dark:text-slate-400"
                />
                Configuración
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="flex w-full cursor-pointer items-center rounded-xl text-rose-800 hover:text-rose-950 dark:text-rose-400 dark:hover:text-rose-200"
            method="post"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Cerrar sesión
        </Link>
    </DropdownMenuItem>
</template>
