( function( wc, blocks, i18n, element, components ) {
    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { __ } = i18n;
    const { createElement } = element;

    registerPaymentMethod( {
        name: 'external_pix',
        label: __( 'Pagamento via Pix', 'woocommerce' ),
        ariaLabel: __( 'Pagamento via Pix usando QRCode', 'woocommerce' ),
        content: createElement( 'div', {}, __( 'Pague com Pix usando um QRCode gerado.', 'woocommerce' ) ),
        edit: createElement( 'div', {}, __( 'Pagamento via Pix.', 'woocommerce' ) ),
        canMakePayment: () => true,
        supports: {
            features: [ 'products', 'blocks' ],
        },
        onPaymentProcessing: ( { processingResponse, setProcessingResponse } ) => {
            // Aqui você pode realizar as ações para processar o pagamento
            setProcessingResponse( { type: 'success' } );
            return processingResponse;
        },
    } );
} )( window.wc, window.wp.blocks, window.wp.i18n, window.wp.element, window.wp.components );
