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
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
</script>

<template>
    <AuthBase
        :title="$t('auth.registerTitle')"
        :description="$t('auth.registerDescription')"
    >
        <Head :title="$t('auth.registerPageTitle')" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <Button
                    type="button"
                    variant="outline"
                    class="w-full"
                    as="a"
                    href="/auth/google"
                >
                    <svg class="mr-2 h-4 w-4" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                        <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                        <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                        <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                    </svg>
                    {{ $t('auth.signUpWithGoogle') }}
                </Button>

                <div class="relative my-2">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t"></span>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-background px-2 text-muted-foreground">
                            Atau
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="grid gap-2">
                        <Label for="name">{{ $t('auth.fullName') }}</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            name="name"
                            :placeholder="$t('auth.fullNamePlaceholder')"
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
                        <div class="relative">
                            <Input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                :tabindex="3"
                                autocomplete="new-password"
                                name="password"
                                :placeholder="$t('auth.minChars')"
                                class="pr-10"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                                :tabindex="-1"
                            >
                                <Eye v-if="!showPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >{{ $t('auth.confirmPassword') }}</Label
                        >
                        <div class="relative">
                            <Input
                                id="password_confirmation"
                                :type="showPasswordConfirmation ? 'text' : 'password'"
                                required
                                :tabindex="4"
                                autocomplete="new-password"
                                name="password_confirmation"
                                :placeholder="$t('auth.repeatPassword')"
                                class="pr-10"
                            />
                            <button
                                type="button"
                                @click="showPasswordConfirmation = !showPasswordConfirmation"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                                :tabindex="-1"
                            >
                                <Eye v-if="!showPasswordConfirmation" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </div>

                <div class="border-t pt-6">
                    <h3 class="mb-6 text-sm font-semibold">
                        {{ $t('auth.personaTitle') }}
                    </h3>

                    <div class="grid gap-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="grid gap-2">
                                <Label for="call_preference">{{ $t('auth.callPreference') }}</Label>
                                <Input
                                    id="call_preference"
                                    type="text"
                                    required
                                    :tabindex="5"
                                    name="call_preference"
                                    :placeholder="$t('auth.callPreferencePlaceholder')"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ $t('auth.callPreferenceHelp') }}
                                </p>
                                <InputError :message="errors.call_preference" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="aspri_name">{{ $t('auth.assistantName') }}</Label>
                                <Input
                                    id="aspri_name"
                                    type="text"
                                    required
                                    :tabindex="6"
                                    name="aspri_name"
                                    :placeholder="$t('auth.assistantNamePlaceholder')"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ $t('auth.assistantNameHelp') }}
                                </p>
                                <InputError :message="errors.aspri_name" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="aspri_persona">{{ $t('auth.assistantPersona') }}</Label>
                            <Input
                                id="aspri_persona"
                                type="text"
                                required
                                :tabindex="7"
                                name="aspri_persona"
                                :placeholder="$t('auth.assistantPersonaPlaceholder')"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ $t('auth.assistantPersonaHelp') }}
                            </p>
                            <InputError :message="errors.aspri_persona" />
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-center text-xs text-muted-foreground">
                    {{ $t('auth.agreementText') }}
                    <TextLink href="/terms-of-service" class="underline">{{ $t('legal.termsOfService') }}</TextLink>
                    {{ $t('auth.agreementAnd') }}
                    <TextLink href="/privacy-policy" class="underline">{{ $t('legal.privacyPolicy') }}</TextLink>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full md:w-auto md:self-end"
                    :tabindex="8"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('auth.createAccount') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ $t('auth.hasAccount') }}
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="9"
                    >{{ $t('auth.loginHere') }}</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
