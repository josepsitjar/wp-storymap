document.addEventListener( 'click', ( event ) => {
	const quickEditButton = event.target.closest(
		'.wp-storymap-point-quick-edit'
	);
	const cancelButton = event.target.closest(
		'.wp-storymap-point-quick-edit-cancel'
	);

	if ( quickEditButton ) {
		event.preventDefault();
		const pointRow = quickEditButton.closest( 'tr' );
		const editRow = pointRow?.nextElementSibling;

		if (
			editRow?.classList.contains( 'wp-storymap-point-quick-edit-row' )
		) {
			const shouldOpen = editRow.classList.contains( 'hidden' );
			editRow.classList.toggle( 'hidden', ! shouldOpen );
			quickEditButton.setAttribute(
				'aria-expanded',
				String( shouldOpen )
			);
			if ( shouldOpen ) {
				editRow.querySelector( 'input[name="name"]' )?.focus();
			}
		}
	}

	if ( cancelButton ) {
		event.preventDefault();
		const editRow = cancelButton.closest(
			'.wp-storymap-point-quick-edit-row'
		);
		const actionRow = editRow?.previousElementSibling;

		if ( editRow ) {
			editRow.classList.add( 'hidden' );
		}
		actionRow
			?.querySelector( '.wp-storymap-point-quick-edit' )
			?.setAttribute( 'aria-expanded', 'false' );
	}
} );
