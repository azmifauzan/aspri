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
