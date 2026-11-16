@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-checkout-address-form-template"
    >
        <div class="mt-2 max-md:mt-3">
            <x-shop::form.control-group class="hidden">
                <x-shop::form.control-group.control
                    type="text"
                    ::name="controlName + '.id'"
                    ::value="address.id"
                />
            </x-shop::form.control-group>

            <!-- First Name -->
            <div class="grid grid-cols-2 gap-x-5 max-md:grid-cols-1">
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="required !mt-0">
                        @lang('shop::app.checkout.onepage.address.first-name')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.first_name'"
                        ::value="address.first_name"
                        autocomplete="given-name"
                        rules="required"
                        :label="trans('shop::app.checkout.onepage.address.first-name')"
                        :placeholder="trans('shop::app.checkout.onepage.address.first-name')"
                    />

                    <x-shop::form.control-group.error ::name="controlName + '.first_name'" />
                </x-shop::form.control-group>

                {!! view_render_event('qubix.shop.checkout.onepage.address.form.first_name.after') !!}

                <!-- Last Name -->
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="required !mt-0">
                        @lang('shop::app.checkout.onepage.address.last-name')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.last_name'"
                        ::value="address.last_name"
                        autocomplete="family-name"
                        rules="required"
                        :label="trans('shop::app.checkout.onepage.address.last-name')"
                        :placeholder="trans('shop::app.checkout.onepage.address.last-name')"
                    />

                    <x-shop::form.control-group.error ::name="controlName + '.last_name'" />
                </x-shop::form.control-group>

                {!! view_render_event('qubix.shop.checkout.onepage.address.form.last_name.after') !!}
            </div>

            <!-- Email -->
            <x-shop::form.control-group>
                <x-shop::form.control-group.label class="required !mt-0">
                    @lang('shop::app.checkout.onepage.address.email')
                </x-shop::form.control-group.label>

                <x-shop::form.control-group.control
                    type="email"
                    ::name="controlName + '.email'"
                    ::value="address.email"
                    autocomplete="email"
                    rules="required|email"
                    :label="trans('shop::app.checkout.onepage.address.email')"
                    placeholder="email@example.com"
                />

                <x-shop::form.control-group.error ::name="controlName + '.email'" />
            </x-shop::form.control-group>

            {!! view_render_event('qubix.shop.checkout.onepage.address.form.email.after') !!}

            <!-- Street Address -->
            <x-shop::form.control-group>
                <x-shop::form.control-group.label class="required !mt-0">
                    @lang('shop::app.checkout.onepage.address.street-address')
                </x-shop::form.control-group.label>

                <x-shop::form.control-group.control
                    type="text"
                    ::name="controlName + '.address.[0]'"
                    ::value="address.address[0]"
                    autocomplete="address-line1"
                    rules="required|address"
                    :label="trans('shop::app.checkout.onepage.address.street-address')"
                    :placeholder="trans('shop::app.checkout.onepage.address.street-address')"
                />

                <x-shop::form.control-group.error
                    class="mb-2"
                    ::name="controlName + '.address.[0]'"
                />

                @if (core()->getConfigData('customer.address.information.street_lines') > 1)
                    @for ($i = 1; $i < core()->getConfigData('customer.address.information.street_lines'); $i++)
                        <x-shop::form.control-group.control
                            type="text"
                            ::name="controlName + '.address.[{{ $i }}]'"
                            rules="address"
                            :label="trans('shop::app.checkout.onepage.address.street-address')"
                            :placeholder="trans('shop::app.checkout.onepage.address.street-address')"
                        />

                        <x-shop::form.control-group.error
                            class="mb-2"
                            ::name="controlName + '.address.[{{ $i }}]'"
                        />
                    @endfor
                @endif
            </x-shop::form.control-group>

            {!! view_render_event('qubix.shop.checkout.onepage.address.form.address.after') !!}

            <div class="grid grid-cols-2 gap-x-5 max-md:grid-cols-1">
                <!-- Country -->
                <x-shop::form.control-group class="!mb-4">
                    <x-shop::form.control-group.label class="{{ core()->isCountryRequired() ? 'required' : '' }} !mt-0">
                        @lang('shop::app.checkout.onepage.address.country')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="select"
                        ::name="controlName + '.country'"
                        ::value="address.country || defaultCountry"
                        v-model="selectedCountry"
                        autocomplete="country"
                        rules="{{ core()->isCountryRequired() ? 'required' : '' }}"
                        :label="trans('shop::app.checkout.onepage.address.country')"
                        :placeholder="trans('shop::app.checkout.onepage.address.country')"
                    >
                        <option value="">
                            @lang('shop::app.checkout.onepage.address.select-country')
                        </option>

                        <option
                            v-for="country in countries"
                            :value="country.code"
                        >
                            @{{ country.name }}
                        </option>
                    </x-shop::form.control-group.control>

                    <x-shop::form.control-group.error ::name="controlName + '.country'" />
                </x-shop::form.control-group>

                {!! view_render_event('qubix.shop.checkout.onepage.address.form.country.after') !!}

                <!-- State -->
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="{{ core()->isStateRequired() ? 'required' : '' }} !mt-0">
                        @lang('shop::app.checkout.onepage.address.state')
                    </x-shop::form.control-group.label>

                    <template v-if="states">
                        <template v-if="haveStates">
                            <x-shop::form.control-group.control
                                type="select"
                                ::name="controlName + '.state'"
                                rules="{{ core()->isStateRequired() ? 'required' : '' }}"
                                ::value="address.state"
                                :label="trans('shop::app.checkout.onepage.address.state')"
                                :placeholder="trans('shop::app.checkout.onepage.address.state')"
                            >
                                <option value="">
                                    @lang('shop::app.checkout.onepage.address.select-state')
                                </option>

                                <option
                                    v-for='(state, index) in states[selectedCountry]'
                                    :value="state.code"
                                >
                                    @{{ state.default_name }}
                                </option>
                            </x-shop::form.control-group.control>
                        </template>

                        <template v-else>
                            <x-shop::form.control-group.control
                                type="text"
                                ::name="controlName + '.state'"
                                ::value="address.state"
                                rules="{{ core()->isStateRequired() ? 'required' : '' }}"
                                :label="trans('shop::app.checkout.onepage.address.state')"
                                :placeholder="trans('shop::app.checkout.onepage.address.state')"
                            />
                        </template>
                    </template>

                    <x-shop::form.control-group.error ::name="controlName + '.state'" />
                </x-shop::form.control-group>

                {!! view_render_event('qubix.shop.checkout.onepage.address.form.state.after') !!}
            </div>

            <div class="grid grid-cols-2 gap-x-5 max-md:grid-cols-1">
                <!-- City -->
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="required !mt-0">
                        @lang('shop::app.checkout.onepage.address.city')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.city'"
                        ::value="address.city"
                        autocomplete="address-level2"
                        rules="required"
                        :label="trans('shop::app.checkout.onepage.address.city')"
                        :placeholder="trans('shop::app.checkout.onepage.address.city')"
                    />

                    <x-shop::form.control-group.error ::name="controlName + '.city'" />
                </x-shop::form.control-group>

                {!! view_render_event('qubix.shop.checkout.onepage.address.form.city.after') !!}

                <!-- Postcode -->
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="{{ core()->isPostCodeRequired() ? 'required' : '' }} !mt-0">
                        <template v-if="isIndia">@lang('shop::app.checkout.onepage.address.pin-code')</template>
                        <template v-else>@lang('shop::app.checkout.onepage.address.postcode')</template>
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.postcode'"
                        ::value="address.postcode"
                        ::inputmode="isIndia ? 'numeric' : 'text'"
                        autocomplete="postal-code"
                        rules="{{ core()->isPostCodeRequired() ? 'required' : '' }}|postcode"
                        ::label="isIndia ? {{ json_encode(trans('shop::app.checkout.onepage.address.pin-code')) }} : {{ json_encode(trans('shop::app.checkout.onepage.address.postcode')) }}"
                        ::placeholder="isIndia ? {{ json_encode(trans('shop::app.checkout.onepage.address.pin-code')) }} : {{ json_encode(trans('shop::app.checkout.onepage.address.postcode')) }}"
                    />

                    <x-shop::form.control-group.error ::name="controlName + '.postcode'" />
                </x-shop::form.control-group>

                {!! view_render_event('qubix.shop.checkout.onepage.address.form.postcode.after') !!}
            </div>

            <!-- Phone Number -->
            <x-shop::form.control-group>
                <x-shop::form.control-group.label class="required !mt-0">
                    @lang('shop::app.checkout.onepage.address.telephone')
                </x-shop::form.control-group.label>

                <x-shop::form.control-group.control
                    type="tel"
                    ::name="controlName + '.phone'"
                    ::value="address.phone"
                    autocomplete="tel"
                    rules="required|phone"
                    :label="trans('shop::app.checkout.onepage.address.telephone')"
                    :placeholder="trans('shop::app.checkout.onepage.address.telephone')"
                />

                <x-shop::form.control-group.error ::name="controlName + '.phone'" />
            </x-shop::form.control-group>

            {!! view_render_event('qubix.shop.checkout.onepage.address.form.phone.after') !!}

            <!-- Company Name (optional; the storefront asks for no tax number) -->
            <x-shop::form.control-group>
                <x-shop::form.control-group.label>
                    @lang('shop::app.checkout.onepage.address.company-name')
                </x-shop::form.control-group.label>

                <x-shop::form.control-group.control
                    type="text"
                    ::name="controlName + '.company_name'"
                    ::value="address.company_name"
                    autocomplete="organization"
                    :placeholder="trans('shop::app.checkout.onepage.address.company-name')"
                />
            </x-shop::form.control-group>

            {!! view_render_event('qubix.shop.checkout.onepage.address.form.company_name.after') !!}
        </div>
    </script>

    <script type="module">
        app.component('v-checkout-address-form', {
            template: '#v-checkout-address-form-template',

            props: {
                controlName: {
                    type: String,
                    required: true,
                },

                address: {
                    type: Object,

                    default: () => ({
                        id: 0,
                        company_name: '',
                        first_name: '',
                        last_name: '',
                        email: '',
                        address: [],
                        country: '',
                        state: '',
                        city: '',
                        postcode: '',
                        phone: '',
                    }),
                },
            },

            data() {
                {{-- The store's own country (admin: Sales > Shipping > Origin) preselects an empty address. --}}
                const defaultCountry = @json(core()->getConfigData('sales.shipping.origin.country') ?? '');

                return {
                    defaultCountry,

                    selectedCountry: this.address.country || defaultCountry,

                    countries: [],

                    states: null,
                }
            },

            computed: {
                haveStates() {
                    return !! this.states[this.selectedCountry]?.length;
                },

                isIndia() {
                    return this.selectedCountry === 'IN';
                },
            },

            mounted() {
                this.getCountries();

                this.getStates();
            },

            methods: {
                getCountries() {
                    this.$axios.get("{{ route('shop.api.core.countries') }}")
                        .then(response => {
                            this.countries = response.data.data;
                        })
                        .catch(() => {});
                },

                getStates() {
                    this.$axios.get("{{ route('shop.api.core.states') }}")
                        .then(response => {
                            this.states = response.data.data;
                        })
                        .catch(() => {});
                },
            }
        });
    </script>
@endPushOnce
