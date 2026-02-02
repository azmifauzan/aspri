<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

type Profile = {
    birth_day: number | null;
    birth_month: number | null;
    call_preference: string | null;
    aspri_name: string | null;
    aspri_persona: string | null;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    profile: Profile | null;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;

// Reactive form data for select components
const birthDay = ref(props.profile?.birth_day?.toString() ?? '');
const birthMonth = ref(props.profile?.birth_month?.toString() ?? '');

const months = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const days = Array.from({ length: 31 }, (_, i) => ({
    value: (i + 1).toString(),
    label: (i + 1).toString(),
}));

const personaOptions = [
    { value: 'asisten yang ramah dan membantu', label: 'Ramah & Membantu' },
    { value: 'asisten profesional yang efisien', label: 'Profesional & Efisien' },
    { value: 'teman yang santai dan asyik', label: 'Santai & Asyik' },
    { value: 'mentor yang bijaksana', label: 'Bijaksana & Mendidik' },
];

const selectedPersona = ref(props.profile?.aspri_persona ?? 'asisten yang ramah dan membantu');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile Settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Profile information"
                    description="Update your name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Nama lengkap"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Alamat email"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Email kamu belum diverifikasi.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Klik di sini untuk kirim ulang email verifikasi.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            Link verifikasi baru telah dikirim ke alamat email kamu.
                        </div>
                    </div>

                    <Separator />

                    <Heading
                        variant="small"
                        title="Tanggal Lahir"
                        description="Digunakan untuk ucapan ulang tahun"
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="birth_day">Tanggal</Label>
                            <input type="hidden" name="birth_day" :value="birthDay" />
                            <Select v-model="birthDay">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih tanggal" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="day in days"
                                        :key="day.value"
                                        :value="day.value"
                                    >
                                        {{ day.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError class="mt-2" :message="errors.birth_day" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="birth_month">Bulan</Label>
                            <input type="hidden" name="birth_month" :value="birthMonth" />
                            <Select v-model="birthMonth">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih bulan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="month in months"
                                        :key="month.value"
                                        :value="month.value"
                                    >
                                        {{ month.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError class="mt-2" :message="errors.birth_month" />
                        </div>
                    </div>

                    <Separator />

                    <Heading
                        variant="small"
                        title="Personalisasi Asisten"
                        description="Sesuaikan cara asisten AI berkomunikasi denganmu"
                    />

                    <div class="grid gap-2">
                        <Label for="call_preference">Panggilan</Label>
                        <Input
                            id="call_preference"
                            class="mt-1 block w-full"
                            name="call_preference"
                            :default-value="profile?.call_preference ?? 'Kak'"
                            required
                            placeholder="contoh: Kak, Mas, Mbak, Bos"
                        />
                        <p class="text-xs text-muted-foreground">
                            Asisten akan memanggilmu dengan panggilan ini
                        </p>
                        <InputError class="mt-2" :message="errors.call_preference" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="aspri_name">Nama Asisten</Label>
                        <Input
                            id="aspri_name"
                            class="mt-1 block w-full"
                            name="aspri_name"
                            :default-value="profile?.aspri_name ?? 'ASPRI'"
                            required
                            placeholder="contoh: ASPRI, Jarvis, Friday"
                        />
                        <p class="text-xs text-muted-foreground">
                            Berikan nama untuk asisten pribadimu
                        </p>
                        <InputError class="mt-2" :message="errors.aspri_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="aspri_persona">Kepribadian Asisten</Label>
                        <input type="hidden" name="aspri_persona" :value="selectedPersona" />
                        <Select v-model="selectedPersona">
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih kepribadian" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in personaOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Pilih gaya komunikasi yang kamu sukai
                        </p>
                        <InputError class="mt-2" :message="errors.aspri_persona" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Simpan</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Tersimpan.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
