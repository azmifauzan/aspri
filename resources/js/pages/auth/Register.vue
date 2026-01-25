<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase
        title="Daftar Akun Baru"
        description="Buat akun ASPRI Anda dan lengkapi profil persona Anda"
    >
        <Head title="Daftar" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="grid gap-2">
                        <Label for="name">Nama Lengkap</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            name="name"
                            placeholder="Nama lengkap Anda"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            :tabindex="2"
                            autocomplete="email"
                            name="email"
                            placeholder="email@example.com"
                        />
                        <InputError :message="errors.email" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            name="password"
                            placeholder="Minimal 8 karakter"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Konfirmasi Password</Label
                        >
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            name="password_confirmation"
                            placeholder="Ulangi password"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </div>

                <div class="border-t pt-6">
                    <h3 class="mb-6 text-sm font-semibold">
                        Pengaturan Persona Asisten
                    </h3>

                    <div class="grid gap-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="grid gap-2">
                                <Label for="birth_date">Tanggal Lahir</Label>
                                <Input
                                    id="birth_date"
                                    type="number"
                                    required
                                    :tabindex="5"
                                    name="birth_day"
                                    placeholder="1-31"
                                    min="1"
                                    max="31"
                                />
                                <InputError :message="errors.birth_day" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="birth_month">Bulan Lahir</Label>
                                <Input
                                    id="birth_month"
                                    type="number"
                                    required
                                    :tabindex="6"
                                    name="birth_month"
                                    placeholder="1-12"
                                    min="1"
                                    max="12"
                                />
                                <InputError :message="errors.birth_month" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="grid gap-2">
                                <Label for="call_preference">Cara Panggilan</Label>
                                <Input
                                    id="call_preference"
                                    type="text"
                                    required
                                    :tabindex="7"
                                    name="call_preference"
                                    placeholder="Contoh: Kak, Bapak, Ibu, Mas, Mbak"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Bagaimana asisten memanggil Anda
                                </p>
                                <InputError :message="errors.call_preference" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="aspri_name">Nama Asisten</Label>
                                <Input
                                    id="aspri_name"
                                    type="text"
                                    required
                                    :tabindex="8"
                                    name="aspri_name"
                                    placeholder="Contoh: ASPRI, Jarvis, Friday"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Nama untuk asisten pribadi Anda
                                </p>
                                <InputError :message="errors.aspri_name" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="aspri_persona">Persona Asisten</Label>
                            <Input
                                id="aspri_persona"
                                type="text"
                                required
                                :tabindex="9"
                                name="aspri_persona"
                                placeholder="Contoh: pria, wanita, profesional, santai"
                            />
                            <p class="text-xs text-muted-foreground">
                                Kepribadian asisten (pria, wanita, kucing, anjing,
                                atau custom)
                            </p>
                            <InputError :message="errors.aspri_persona" />
                        </div>
                    </div>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full md:w-auto md:self-end"
                    :tabindex="10"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Buat Akun
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Sudah punya akun?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="11"
                    >Masuk di sini</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
