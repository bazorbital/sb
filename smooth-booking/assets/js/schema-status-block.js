( function( wp ) {
    const { registerBlockType } = wp.blocks;
    const { __ } = wp.i18n;
    const { ToggleControl } = wp.components;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { Fragment } = wp.element;

    registerBlockType( 'smooth-booking/schema-status', {
        title: __( 'Smooth Booking Schema Status', 'smooth-booking' ),
        icon: 'database',
        category: 'widgets',
        attributes: {
            showMissingOnly: {
                type: 'boolean',
                default: false,
            },
        },
        edit: ( props ) => {
            const { attributes, setAttributes } = props;

            return (
                wp.element.createElement( Fragment, {},
                    wp.element.createElement( InspectorControls, {},
                        wp.element.createElement( ToggleControl, {
                            label: __( 'Show only missing tables', 'smooth-booking' ),
                            checked: !! attributes.showMissingOnly,
                            onChange: ( value ) => setAttributes( { showMissingOnly: value } ),
                        } )
                    ),
                    wp.element.createElement( 'p', {}, __( 'Schema status will render on the frontend.', 'smooth-booking' ) )
                )
            );
        },
        save: () => null,
    } );
} )( window.wp );
