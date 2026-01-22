import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting('blikk_payment_data', {});

const defaultLabel = 'Blikk Payment';

const label = decodeEntities(settings.title) || defaultLabel;

/**
 * Content component for Blikk Payment method
 */
const Content = () => {
    return decodeEntities(settings.description || '');
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
