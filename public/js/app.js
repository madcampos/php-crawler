/**
 * @typedef {Object} CrawledImage
 * @property {string} src
 * @property {boolean} isExternal
 */

/**
 * @typedef {Object} CrawledLink
 * @property {string} href
 * @property {boolean} isExternal
 */

/**
 * @typedef {Object} CrawledPage
 * @property {string} url
 * @property {number} loadTimeInMs
 * @property {number} wordCount
 * @property {string} title
 * @property {Array<CrawledImage>} images
 * @property {Array<CrawledLink>} links
 */

/**
 * @typedef {Object} Results
 * @property {string} url
 * @property {number} pagesCrawled
 * @property {Object} averages
 * @property {number} averages.loadTimeInMs
 * @property {number} averages.wordCount
 * @property {number} averages.titleLength
 * @property {number} averages.images
 * @property {number} averages.links
 * @property {Array<CrawledPage>} pages
 */

function toggleInputs() {
	const inputs = document.querySelectorAll('form :is(input, button)');

	inputs.forEach((element) => {
		element.disabled = !element.disabled;
	});
}

/**
 * @param {HTMLFormElement} form
 * @param {FormData} formData
 */
async function fetchData(form, formData) {
	const response = await fetch(form.action, {
		method: form.method,
		body: formData,
	});

	const data = await response.json();

	return data;
}

/**
 * @param {Array<CrawledLink>} links
 * @param {string} url
 */
function createLinksDialog(links, url) {
	return `
		<dialog>
			<header>
				<h2>Links found on the page</h2>
				<button type="button" class="close-dialog">Close</button>
			</header>
			<article>
				<ul>
					${links.map((link) => `
						<li>
							<a href="${new URL(link.href, url).toString()}" target="_blank" rel="noopener noreferrer">${link.isExternal ? '(🌎 external) ' : ''}${link.href}</a>
						</li>
					`).join('')}
				</ul>
			</article>
		</dialog>
	`;
}

/**
 * @param {Array<CrawledImage>} images
 * @param {string} url
 */
function createImagesDialog(images, url) {
	return `
		<dialog>
			<header>
				<h2>Images found on the page</h2>
				<button type="button" class="close-dialog">Close</button>
			</header>
			<article>
				<ul>
					${images.map((image) => `
						<li>
							<img src="${new URL(image.src, url).toString()}" role="presentation"/>
						</li>
					`).join('')}
				</ul>
			</article>
		</dialog>
	`;
}

function handleDialogOpen(evt) {
	if (!evt.target.matches('.open-dialog')) {
		return;
	}

	const dialog = evt.target.nextElementSibling;

	dialog.showModal();
}

function handleDialogClose(evt) {
	if (!evt.target.matches('.close-dialog')) {
		return;
	}

	const dialog = evt.target.closest('dialog');

	dialog.close();
}

/**
 * @param {Results} data
 */
function formatResultsTable(data) {
	const table = document.querySelector('#page-results');

	table.querySelector('tbody').innerHTML = '';

	data.pages.forEach((page) => {
		const row = document.createElement('tr');

		row.innerHTML = `
			<td>${page.url}</td>
			<td>${page.title}</td>
			<td>${page.wordCount}</td>
			<td>${page.loadTimeInMs}ms</td>
			<td>
				${page.images.length}
				<button type="button" class="open-dialog">View images</button>
				${createImagesDialog(page.images, page.url)}
			</td>
			<td>
				${page.links.length}
				<button type="button" class="open-dialog">View links</button>
				${createLinksDialog(page.links, page.url)}
			</td>
		`;

		table.querySelector('tbody').appendChild(row);
	});
}

function formatAveragesTable(data) {
	const table = document.querySelector('#averages-results');

	table.querySelector('tbody').innerHTML = '';

	const row = document.createElement('tr');

	row.innerHTML = `
		<td>${data.averages.titleLength}</td>
		<td>${data.averages.wordCount}</td>
		<td>${data.averages.loadTimeInMs}ms</td>
		<td>${data.averages.images}</td>
		<td>${data.averages.links}</td>
	`;

	table.querySelector('tbody').appendChild(row);
}

/**
 * @param {SubmitEvent} evt
 */
async function crawlPage(evt) {
	evt.preventDefault();
	evt.stopPropagation();

	const resultsContainer = document.querySelector('#results');

	try {
		/** @type {HTMLFormElement} */
		const form = evt.target;
		const formData = new FormData(form);

		toggleInputs();
		resultsContainer.removeAttribute('hidden');
		resultsContainer.classList.add('loading');

		/** @type {Results} */
		const results = await fetchData(form, formData);

		formatResultsTable(results);
		formatAveragesTable(results);

		resultsContainer.classList.remove('error');
		resultsContainer.classList.remove('loading');
	} catch (error) {
		console.error(error);

		resultsContainer.classList.add('error');
		resultsContainer.classList.remove('loading');

		resultsContainer.querySelector('#results-error p').innerHTML = 'Something went wrong. Please try again.';
	} finally {
		toggleInputs();
	}
}

document.addEventListener('DOMContentLoaded', () => {
	document.querySelector('form')?.addEventListener('submit', crawlPage);
	document.addEventListener('click', handleDialogOpen);
	document.addEventListener('click', handleDialogClose);
});
