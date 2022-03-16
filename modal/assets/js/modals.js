/**
 * Add an event
 * @param event
 * @param selector
 * @param callback
 */
// Needs to be outside of document ready
function rsssl_addEvent(event, selector, callback ) {
    document.addEventListener(event, e => {
        if ( e.target.closest(selector) ) {
            callback(e);
        }
    });
}

rsssl_addEvent('click', '.rsssl-modal-backdrop', function(e) {
    rsssl_RemoveBackdrop();
});

function rsssl_AddBackdrop() {
    let backdrop = document.createElement('div');
    backdrop.className = 'rsssl-modal-backdrop';
    document.body.appendChild(backdrop);
}

function rsssl_RemoveBackdrop() {
    let modals = document.querySelectorAll('.rsssl-modal');

    // Foreach
    modals.forEach( function ( modal ) {
        modal.style.display = 'none';
    });

    let backdrop = document.querySelector('.rsssl-modal-backdrop');
    backdrop.remove();
}