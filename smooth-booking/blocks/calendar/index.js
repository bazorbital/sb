( function ( blocks, element, components, serverSideRender ) {
var el = element.createElement;
var TextControl = components.TextControl;

blocks.registerBlockType( 'smooth-booking/calendar', {
title: 'Smooth Booking Calendar',
editsupports: { inserter: true },
edit: function ( props ) {
return el(
'section',
{},
el( TextControl, {
label: 'Employee ID',
value: props.attributes.employee,
onChange: function ( value ) {
props.setAttributes( { employee: parseInt( value || 0, 10 ) } );
},
} ),
el( serverSideRender, {
block: 'smooth-booking/calendar',
attributes: props.attributes,
} )
);
},
save: function () {
return null;
},
} );
} )( window.wp.blocks, window.wp.element, window.wp.components, window.wp.serverSideRender );
