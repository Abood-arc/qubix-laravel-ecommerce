{!! view_render_event('qubix.shop.checkout.onepage.payment_methods.before') !!}

<v-payment-methods
    :methods="paymentMethods"
    @processing="stepForward"
    @processed="stepProcessed"
>
    <x-shop::shimmer.checkout.onepage.payment-method />
</v-payment-methods>

{!! view_render_event('qubix.shop.checkout.onepage.payment_methods.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-payment-methods-template"
    >
        <div class="mb-7 max-md:last:!mb-0">
            <template v-if="! methods">
                <!-- Payment Method shimmer Effect -->
                <x-shop::shimmer.checkout.onepage.payment-method />
            </template>
    
            <template v-else>
                {!! view_render_event('qubix.shop.checkout.onepage.payment_method.accordion.before') !!}

                <!-- Accordion Blade Component -->
                <x-shop::accordion class="overflow-hidden !border-b-0 max-md:rounded-lg max-md:!border-none max-md:!bg-gray-100">
                    <!-- Accordion Blade Component Header -->
                    <x-slot:header class="px-0 py-4 max-md:p-3 max-md:text-sm max-md:font-medium max-sm:p-2">
                        
                        <div class="flex items-center justify-between">
                            <h2 class="text-2xl font-medium max-md:text-base">
                                @lang('shop::app.checkout.onepage.payment.payment-method')
                            </h2>
                        </div>
                    </x-slot>
    
                    <!-- Accordion Blade Component Content -->
                    <x-slot:content class="mt-8 !p-0 max-md:mt-0 max-md:rounded-t-none max-md:border max-md:border-t-0 max-md:!p-4">
                        <div class="flex flex-wrap gap-7 max-md:gap-4 max-sm:gap-2.5">
                            {{-- A disabled method is shown greyed out with its reason and cannot be picked. --}}
                            <div 
                                class="relative max-md:max-w-full max-md:flex-auto"
                                :class="payment.disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
                                v-for="(payment, index) in methods"
                            >
                                {!! view_render_event('qubix.shop.checkout.payment-method.before') !!}

                                <input 
                                    type="radio" 
                                    name="payment[method]" 
                                    :value="payment.payment"
                                    :id="payment.method"
                                    class="peer hidden"
                                    :disabled="payment.disabled"
                                    @change="store(payment)"
                                >
    
                                <label 
                                    :for="payment.method" 
                                    class="icon-radio-unselect peer-checked:icon-radio-select absolute top-5 text-2xl text-navyBlue ltr:right-5 rtl:left-5"
                                    :class="payment.disabled ? 'cursor-not-allowed' : 'cursor-pointer'"
                                >
                                </label>

                                <label 
                                    :for="payment.method" 
                                    class="block w-[190px] rounded-xl border border-zinc-200 p-5 max-md:flex max-md:w-full max-md:gap-5 max-md:rounded-lg max-sm:gap-4 max-sm:px-4 max-sm:py-2.5"
                                    :class="payment.disabled ? 'cursor-not-allowed' : 'cursor-pointer'"
                                    :aria-disabled="payment.disabled ? 'true' : null"
                                >
                                    {!! view_render_event('qubix.shop.checkout.onepage.payment-method.image.before') !!}

                                    <img
                                        class="max-h-11 max-w-14"
                                        :src="payment.image"
                                        width="55"
                                        height="55"
                                        :alt="payment.method_title"
                                        :title="payment.method_title"
                                    />

                                    {!! view_render_event('qubix.shop.checkout.onepage.payment-method.image.after') !!}

                                    {{-- On phones the text sits beside the image; it stops short of the radio. --}}
                                    <div class="max-md:ltr:pr-8 max-md:rtl:pl-8">
                                        {!! view_render_event('qubix.shop.checkout.onepage.payment-method.title.before') !!}

                                        <p class="mt-1.5 text-sm font-semibold max-md:mt-1 max-sm:mt-0">
                                            @{{ payment.method_title }}
                                        </p>

                                        <p
                                            class="mt-1.5 text-xs font-medium text-red-600 max-md:mt-1 max-sm:mt-0"
                                            v-if="payment.disabled"
                                        >
                                            @{{ payment.reason }}
                                        </p>
                                        
                                        {!! view_render_event('qubix.shop.checkout.onepage.payment-method.title.after') !!}

                                        {!! view_render_event('qubix.shop.checkout.onepage.payment-method.description.before') !!}

                                        <p class="mt-2.5 text-xs font-medium text-zinc-500 max-md:mt-1 max-sm:mt-0">
                                            @{{ payment.description }}
                                        </p> 

                                        {!! view_render_event('qubix.shop.checkout.onepage.payment-method.description.after') !!}
    
                                    </div>
                                </label>

                                {!! view_render_event('qubix.shop.checkout.payment-method.after') !!}

                                <!-- Todo implement the additionalDetails -->
                                {{-- \DigitalLabs\Payment\Payment::getAdditionalDetails($payment['method'] --}}
                            </div>
                        </div>
                    </x-slot>
                </x-shop::accordion>

                {!! view_render_event('qubix.shop.checkout.onepage.payment_method.accordion.after') !!}
            </template>
        </div>
    </script>

    <script type="module">
        app.component('v-payment-methods', {
            template: '#v-payment-methods-template',

            props: {
                methods: {
                    type: Object,
                    required: true,
                    default: () => null,
                },
            },

            emits: ['processing', 'processed'],

            methods: {
                store(selectedMethod) {
                    if (selectedMethod.disabled) {
                        return;
                    }

                    this.$emit('processing', 'review');

                    this.$axios.post("{{ route('shop.checkout.onepage.payment_methods.store') }}", {
                            payment: selectedMethod
                        })
                        .then(response => {
                            this.$emit('processed', response.data.cart);

                            // Used in mobile view. 
                            if (window.innerWidth <= 768) {
                                window.scrollTo({
                                    top: document.body.scrollHeight,
                                    behavior: 'smooth'
                                });
                            }
                        })
                        .catch(error => {
                            this.$emit('processing', 'payment');

                            if (error.response.data.redirect_url) {
                                window.location.href = error.response.data.redirect_url;
                            }
                        });
                },
            },
        });
    </script>
@endPushOnce
