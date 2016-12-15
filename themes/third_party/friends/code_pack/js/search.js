/**
 * Safari Search Field
 * Replaces the search text input field with the really pretty one in Safari
 */

if (document.getElementById('keywords'))
{
	searchField = document.getElementById('keywords');
	
	if ((parseInt(navigator.productSub)>=20020000)&&(navigator.vendor.indexOf("Apple Computer")!=-1))
	{
		searchField.setAttribute('type', 'search');
		searchField.setAttribute('autosave', 'reedmaniac_simple_search');
		searchField.setAttribute('results', '10');
		searchField.setAttribute('placeholder', 'Search...');
	}
	else
	{
		searchField.value = 'Search...';
		searchField.onfocus = function() { if (this.value == 'Search...') {this.value = '';} }
		searchField.onblur = function() { if (this.value == '') {this.value = 'Search...';} }
	}
}