<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, nextTick } from 'vue';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

// Estado reativo
const form = useForm({
    matricula: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const capsLockOn = ref(false);
const currentYear = new Date().getFullYear();

// Refs dos elementos
const matriculaInput = ref(null);
const passwordInput = ref(null);

// Métodos
const clearFieldError = (field) => {
    if (form.errors[field]) {
        delete form.errors[field];
    }
};

const handleMatriculaInput = (event) => {
    // Aceita apenas números
    const value = event.target.value.replace(/\D/g, '');
    form.matricula = value;
    clearFieldError('matricula');
};

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const checkCapsLock = (event) => {
    if (event.getModifierState) {
        capsLockOn.value = event.getModifierState('CapsLock');
    }
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
        onError: () => {
            nextTick(() => {
                if (passwordInput.value) {
                    passwordInput.value.focus();
                }
            });
        }
    });
};

onMounted(() => {
    if (matriculaInput.value) {
        matriculaInput.value.focus();
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-4 sm:pt-0 px-4 sm:px-6 bg-gradient-to-b from-neutral-50 to-neutral-100">
        <Head title="Login - VERBO" />

        <!-- Logo e título -->
        <div class="relative z-10 mb-6 sm:mb-8 flex flex-col items-center">
            <img
                src="/images/brasao_pcpb.png"
                alt="Logo PCPB"
                class="w-32 sm:w-40 lg:w-32 h-auto drop-shadow-lg shrink-0"
            />

            <div class="mt-3 text-center">
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-800">VERBO</h1>
                <div class="mt-1 text-md text-gray-600">Vídeo Oitivas Policiais</div>
            </div>
        </div>

        <!-- Card de Login -->
        <div class="w-full max-w-sm sm:max-w-md px-4 sm:px-6 py-6 sm:py-8 bg-white shadow-xl sm:rounded-lg relative z-10 border border-neutral-200">

            <!-- Mensagem de status (sucesso) -->
            <div v-if="status" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-500 mr-2 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="text-sm text-green-600">
                        {{ status }}
                    </div>
                </div>
            </div>

            <!-- Mensagens de erro gerais -->
            <div v-if="form.errors.general || (Object.keys(form.errors).length > 0 && !form.errors.matricula && !form.errors.password)" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-red-500 mr-2 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="text-sm text-red-600">
                        {{ form.errors.general || 'Verifique os dados informados e tente novamente.' }}
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4 sm:space-y-6">
                <!-- Matrícula -->
                <div class="relative">
                    <label for="matricula" class="block text-sm font-medium text-neutral-700 mb-2">
                        Matrícula
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 sm:h-5 sm:w-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                            </svg>
                        </div>
                        <input
                            id="matricula"
                            ref="matriculaInput"
                            v-model="form.matricula"
                            type="text"
                            inputmode="numeric"
                            :class="[
                                'h-11 pl-10 pr-4 block w-full border rounded-md shadow-sm transition-colors duration-200 py-2 sm:py-3 text-sm sm:text-base',
                                form.errors.matricula
                                    ? 'border-red-300 focus:border-red-500 focus:ring-1 focus:ring-red-200'
                                    : 'border-neutral-300 focus:border-neutral-500 focus:ring-1 focus:ring-neutral-200'
                            ]"
                            required
                            autofocus
                            placeholder="Informe sua Matrícula"
                            maxlength="7"
                            :disabled="form.processing"
                            @input="handleMatriculaInput"
                            autocomplete="username"
                        />
                    </div>
                    <div v-if="form.errors.matricula" class="mt-1 sm:mt-2 text-xs sm:text-sm text-red-600">
                        {{ form.errors.matricula }}
                    </div>
                </div>

                <!-- Senha -->
                <div class="relative">
                    <label for="password" class="block text-sm font-medium text-neutral-700 mb-2">
                        Senha
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 sm:h-5 sm:w-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            :class="[
                                'h-11 pl-10 pr-12 block w-full border rounded-md shadow-sm transition-colors duration-200 py-2 sm:py-3 text-sm sm:text-base',
                                form.errors.password
                                    ? 'border-red-300 focus:border-red-500 focus:ring-1 focus:ring-red-200'
                                    : 'border-neutral-300 focus:border-neutral-500 focus:ring-1 focus:ring-neutral-200'
                            ]"
                            required
                            placeholder="••••••••"
                            :disabled="form.processing"
                            autocomplete="current-password"
                            @input="clearFieldError('password')"
                            @keydown="checkCapsLock"
                            @keyup="checkCapsLock"
                            @focus="checkCapsLock"
                            @blur="capsLockOn = false"
                        />
                        <button
                            type="button"
                            @click="togglePasswordVisibility"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-neutral-400 hover:text-neutral-600 focus:outline-none transition-colors"
                            tabindex="-1"
                        >
                            <svg v-if="showPassword" class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                            <svg v-else class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Aviso de Caps Lock -->
                    <div v-if="capsLockOn" class="mt-1 sm:mt-2 flex items-center text-xs sm:text-sm text-amber-600 bg-amber-50 p-2 rounded-md border border-amber-200">
                        <svg class="w-4 h-4 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.664-.833-2.464 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span>Caps Lock está ativo</span>
                    </div>

                    <div v-if="form.errors.password" class="mt-1 sm:mt-2 text-xs sm:text-sm text-red-600">
                        {{ form.errors.password }}
                    </div>
                </div>

                <!-- Lembrar-me -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="rounded border-neutral-300 h-4 w-4 text-gray-600 focus:ring-1 focus:ring-gray-700 transition-colors"
                        />
                        <label for="remember" class="ml-2 text-sm text-neutral-600">Lembrar-me</label>
                    </div>

                    <!-- Link para recuperação de senha -->
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs sm:text-sm text-[#c1a85a] hover:text-[#a8924e] font-medium transition-colors"
                    >
                        Esqueceu a senha?
                    </Link>
                </div>

                <!-- Botão de Login -->
                <div class="flex flex-col space-y-3 sm:space-y-4">
                    <button
                        type="submit"
                        :class="[
                            'w-full flex items-center justify-center space-x-2 px-6 py-2 sm:py-3 bg-[#c1a85a] text-white font-medium rounded-md focus:outline-none transition-all duration-200 text-sm sm:text-base',
                            form.processing
                                ? 'opacity-75 cursor-not-allowed'
                                : 'hover:bg-[#a8924e] hover:shadow-lg transform hover:-translate-y-0.5'
                        ]"
                        :disabled="form.processing"
                    >
                        <span class="uppercase">{{ form.processing ? 'Entrando...' : 'Entrar' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
button,
a {
    transition: all 0.2s ease-in-out;
}

@media (max-width: 480px) {
    .max-w-sm {
        max-width: 95%;
    }
}

@media (prefers-reduced-motion: reduce) {
    button,
    a {
        transition-duration: 0.01ms !important;
    }
}

/* Estilo para o input checkbox */
input[type="checkbox"]:checked {
    border-color: #111111;
}

/* Remove o outline padrão e adiciona um personalizado no foco */
input:focus,
button:focus {
    outline: none;
}
</style>
