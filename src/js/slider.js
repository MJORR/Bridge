/**
 * Bridge — Hero Slider runtime.
 *
 * Loaded only on pages that render the `bridge/hero-slider` block (declared
 * as the block's viewScript). Bundles its own CSS via direct ESM imports.
 * Reads per-slider settings from `data-*` attributes set by render.php.
 */

import Swiper from 'swiper';
import {
	Navigation,
	Pagination,
	Autoplay,
	EffectFade,
	A11y,
} from 'swiper/modules';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import 'swiper/css/effect-fade';

import '../scss/blocks/_hero-slider.scss';

const SELECTOR = '.bridge-hero-slider';

const readBool = (root, key, fallback) => {
	const v = root.dataset[key];
	if (v === undefined) {
		return fallback;
	}
	return v === '1' || v === 'true';
};

const readInt = (root, key, fallback) => {
	const v = parseInt(root.dataset[key], 10);
	return Number.isFinite(v) ? v : fallback;
};

const readText = (root, key, fallback) => {
	const v = root.dataset[key];
	return typeof v === 'string' && v !== '' ? v : fallback;
};

const buildConfig = (root) => {
	const effect = root.dataset.effect || 'fade';
	const autoplay = readBool(root, 'autoplay', true);
	const autoplayDelay = readInt(root, 'autoplayDelay', 6000);
	const loop = readBool(root, 'loop', true);
	const showPagination = readBool(root, 'pagination', true);
	const showNavigation = readBool(root, 'navigation', true);

	const config = {
		// A11y was configured below but never registered here, so none of it
		// ran: the pagination bullets were clickable and unreachable by
		// keyboard, and the messages went nowhere. Registering the module is
		// what makes both true.
		modules: [Navigation, Pagination, Autoplay, EffectFade, A11y],
		effect,
		loop,
		speed: 800,
		grabCursor: true,
		// Read from the markup rather than written here. Swiper's a11y module
		// labels the controls it manages, and hardcoding English here wrote
		// over the translated labels render.php had already put on the
		// buttons. The data attributes carry the theme's own strings, so the
		// slider is translated when the theme is.
		a11y: {
			prevSlideMessage: readText(root, 'labelPrevious', 'Previous slide'),
			nextSlideMessage: readText(root, 'labelNext', 'Next slide'),
			paginationBulletMessage: readText(
				root,
				'labelBullet',
				'Go to slide {{index}}'
			),
		},
	};

	if (effect === 'fade') {
		config.fadeEffect = { crossFade: true };
	}

	if (autoplay) {
		config.autoplay = {
			delay: autoplayDelay,
			disableOnInteraction: false,
			pauseOnMouseEnter: true,
		};
	}

	if (showPagination) {
		const el = root.querySelector('.swiper-pagination');
		if (el) {
			config.pagination = { el, clickable: true };
		}
	}

	if (showNavigation) {
		const nextEl = root.querySelector('.swiper-button-next');
		const prevEl = root.querySelector('.swiper-button-prev');
		if (nextEl && prevEl) {
			config.navigation = { nextEl, prevEl };
		}
	}

	return config;
};

const initBridgeHeroSliders = () => {
	const sliders = document.querySelectorAll(SELECTOR);
	if (!sliders.length) {
		return;
	}

	sliders.forEach((root) => {
		if (root.dataset.bridgeSliderReady === 'true') {
			return;
		}

		const wrapper = root.querySelector('.swiper-wrapper');
		if (wrapper) {
			Array.from(wrapper.children).forEach((child) => {
				child.classList.add('swiper-slide');
			});
		}

		new Swiper(root, buildConfig(root));
		root.dataset.bridgeSliderReady = 'true';
	});
};

if (document.readyState !== 'loading') {
	initBridgeHeroSliders();
} else {
	document.addEventListener('DOMContentLoaded', initBridgeHeroSliders, {
		once: true,
	});
}
