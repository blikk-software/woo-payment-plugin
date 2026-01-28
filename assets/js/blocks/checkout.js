import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { CHECKOUT_STORE_KEY } from '@woocommerce/block-data';

const settings = getSetting('blikk_payment_data', {});

const defaultLabel = 'Blikk Payment';

const label = decodeEntities(settings.title) || defaultLabel;

/**
 * Content component for Blikk Payment method
 */
const Content = ({ eventRegistration, emitResponse }) => {
    const { onPaymentSetup } = eventRegistration;
    
    // Get billing and shipping addresses from WooCommerce checkout store
    const billingAddress = useSelect((select) => {
        return select(CHECKOUT_STORE_KEY).getBillingAddress();
    });
    
    const shippingAddress = useSelect((select) => {
        return select(CHECKOUT_STORE_KEY).getShippingAddress();
    });

    const [phone, setPhone] = useState('');

    // Prefill phone from billing or shipping address
    useEffect(() => {
        const billingPhone = billingAddress?.phone || '';
        const shippingPhone = shippingAddress?.phone || '';
        const initialPhone = billingPhone || shippingPhone;
        
        if (initialPhone && !phone) {
            setPhone(initialPhone);
        }
    }, [billingAddress?.phone, shippingAddress?.phone]);

    // Listen for phone field changes in checkout
    useEffect(() => {
        const handlePhoneChange = () => {
            // Try to get phone from billing field
            const billingPhoneField = document.querySelector('#billing_phone');
            const shippingPhoneField = document.querySelector('#shipping_phone');
            
            if (billingPhoneField && billingPhoneField.value) {
                setPhone(billingPhoneField.value);
            } else if (shippingPhoneField && shippingPhoneField.value) {
                setPhone(shippingPhoneField.value);
            }
        };

        // Listen for changes on billing/shipping phone fields
        const billingPhoneField = document.querySelector('#billing_phone');
        const shippingPhoneField = document.querySelector('#shipping_phone');
        
        if (billingPhoneField) {
            billingPhoneField.addEventListener('change', handlePhoneChange);
            billingPhoneField.addEventListener('input', handlePhoneChange);
        }
        if (shippingPhoneField) {
            shippingPhoneField.addEventListener('change', handlePhoneChange);
            shippingPhoneField.addEventListener('input', handlePhoneChange);
        }

        return () => {
            if (billingPhoneField) {
                billingPhoneField.removeEventListener('change', handlePhoneChange);
                billingPhoneField.removeEventListener('input', handlePhoneChange);
            }
            if (shippingPhoneField) {
                shippingPhoneField.removeEventListener('change', handlePhoneChange);
                shippingPhoneField.removeEventListener('input', handlePhoneChange);
            }
        };
    }, []);

    // Register payment data
    useEffect(() => {
        const unsubscribe = onPaymentSetup(() => {
            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        blikk_phone: phone,
                    },
                },
            };
        });
        return () => unsubscribe();
    }, [onPaymentSetup, phone, emitResponse.responseTypes.SUCCESS]);

    return (
        <div>
            <div className="blikk-payment-description">
                {decodeEntities(settings.description || '')}
            </div>
            <div className="blikk-phone-field-wrapper">
                <div className="blikk-phone-input-container">
                    <label htmlFor="blikk_phone" className="blikk-phone-label">
                        {__('Phone Number', 'blikk-payment-gateway')} <span className="required">*</span>
                    </label>
                    <input
                        type="tel"
                        id="blikk_phone"
                        name="blikk_phone"
                        className="blikk-phone-input"
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        placeholder={__('Enter your phone number', 'blikk-payment-gateway')}
                        required
                    />
                </div>
            </div>
        </div>
    );
};

/**
 * Label component for Blikk Payment method
 */
const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    const iconUrl = settings.icon || '';
    
    if (iconUrl) {
        return (
            <img 
                src={iconUrl} 
                alt={label}
                style={{ 
                    maxHeight: '28px', 
                    height: 'auto',
                    width: 'auto',
                    display: 'block',
                    alignSelf: 'center',
                    objectFit: 'contain',
                    marginLeft: '16px'
                }}
            />
        );
    }
    
    return <PaymentMethodLabel text={label} />;
};

/**
 * Blikk Payment method configuration object.
 */
const BlikkPaymentMethod = {
    name: 'blikk_payment',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports || [],
    },
};

registerPaymentMethod(BlikkPaymentMethod);
