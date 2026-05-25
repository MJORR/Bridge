/**
 * Bridge — Hero Slider runtime.
 *
 * Loaded only on pages that render the `bridge/hero-slider` block (declared
 * as the block's viewScript). Bundles its own CSS via direct ESM imports.
 * Reads per-slider settings from `data-*` attributes set by render.php.
 */

import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, EffectFade } from 'swiper/modules';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import 'swiper/css/effect-fade';

import '../scss/components/_hero-slider.scss';

const SELECTOR = '.bridge-hero-slider';

const readBool = (root, key, fallback) => {
	const v = root.dataset[key];
	if (v === undefined) return fallback;
	return v === '1' || v === 'true';
};

const readInt = (root, key, fallback) => {
	const v = parseInt(root.dataset[key], 10);
	return Number.isFinite(v) ? v : fallback;
};

const buildConfig = (root) => {
	const effect = root.dataset.effect || 'fade';
	const autoplay = readBool(root, 'autoplay', true);
	const autoplayDelay = readInt(root, 'autoplayDelay', 6000);
	const loop = readBool(root, 'loop', true);
	const showPagination = readBool(root, 'pagination', true);
	const showNavigation = readBool(root, 'navigation', true);

	const config = {
		modules: [Navigation, Pagination, Autoplay, EffectFade],
		effect,
		loop,
		speed: 800,
		grabCursor: true,
		a11y: {
			prevSlideMessage: 'Previous slide',
			nextSlideMessage: 'Next slide',
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
	document.addEventListener('DOMContentLoaded', initBridgeHeroSliders, { once: true });
}
