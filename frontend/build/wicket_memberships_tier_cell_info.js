/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/constants.js":
/*!**************************!*\
  !*** ./src/constants.js ***!
  \**************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   API_URL: () => (/* binding */ API_URL),
/* harmony export */   DEFAULT_DATE_FORMAT: () => (/* binding */ DEFAULT_DATE_FORMAT),
/* harmony export */   PLUGIN_API_URL: () => (/* binding */ PLUGIN_API_URL),
/* harmony export */   TIER_CPT_SLUG: () => (/* binding */ TIER_CPT_SLUG),
/* harmony export */   WC_API_V3_URL: () => (/* binding */ WC_API_V3_URL),
/* harmony export */   WC_PRODUCT_TYPES: () => (/* binding */ WC_PRODUCT_TYPES)
/* harmony export */ });
const API_URL = '/wp/v2';
const WC_API_V3_URL = '/wc/v3';
const PLUGIN_API_URL = '/wicket_member/v1';
const TIER_CPT_SLUG = 'wicket_mship_tier';
const DEFAULT_DATE_FORMAT = 'yyyy-MM-dd';
const WC_PRODUCT_TYPES = ['subscription', 'variable-subscription'];

/***/ }),

/***/ "./src/services/api.js":
/*!*****************************!*\
  !*** ./src/services/api.js ***!
  \*****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   fetchMdpPersons: () => (/* binding */ fetchMdpPersons),
/* harmony export */   fetchMemberInfo: () => (/* binding */ fetchMemberInfo),
/* harmony export */   fetchMembers: () => (/* binding */ fetchMembers),
/* harmony export */   fetchMembershipFilters: () => (/* binding */ fetchMembershipFilters),
/* harmony export */   fetchMembershipStatuses: () => (/* binding */ fetchMembershipStatuses),
/* harmony export */   fetchMembershipTiers: () => (/* binding */ fetchMembershipTiers),
/* harmony export */   fetchMemberships: () => (/* binding */ fetchMemberships),
/* harmony export */   fetchProductVariations: () => (/* binding */ fetchProductVariations),
/* harmony export */   fetchTiers: () => (/* binding */ fetchTiers),
/* harmony export */   fetchTiersInfo: () => (/* binding */ fetchTiersInfo),
/* harmony export */   fetchWcProducts: () => (/* binding */ fetchWcProducts),
/* harmony export */   updateMembership: () => (/* binding */ updateMembership),
/* harmony export */   updateMembershipStatus: () => (/* binding */ updateMembershipStatus)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../constants */ "./src/constants.js");




/**
 * Fetch Local Membership Tiers Posts
 */
const fetchTiers = () => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.API_URL}/${_constants__WEBPACK_IMPORTED_MODULE_2__.TIER_CPT_SLUG}`, {
      status: 'publish',
      per_page: 99
    })
  });
};

/**
 * Update Membership Record
 */
const updateMembership = (membershipId, data) => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: `${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/membership_entity/${membershipId}/update`,
    method: 'POST',
    data: data
  });
};

/**
 * Update Membership Status
 */
const updateMembershipStatus = (membershipId, status) => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: `${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/admin/manage_status`,
    method: 'POST',
    data: {
      post_id: membershipId,
      status: status
    }
  });
};

/**
 * Fetch Membership Records
 */
const fetchMemberships = (recordId = null) => {
  if (recordId === null) {
    return;
  }
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/membership_entity`, {
      entity_id: recordId
    })
  });
};

/**
 * Fetch Member Info
 */
const fetchMemberInfo = (recordId = null) => {
  if (recordId === null) {
    return;
  }
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/admin/get_edit_page_info`, {
      entity_id: recordId
    })
  });
};

/**
 * Fetch Available Membership Statuses for a Membership Post
 */
const fetchMembershipStatuses = (postId = null) => {
  if (postId === null) {
    return;
  }
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/admin/status_options`, {
      post_id: postId
    })
  });
};

/**
 * Fetch Members
 */
const fetchMembers = (params = null) => {
  if (params === null) {
    return;
  }
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/memberships`, params)
  });
};

/**
 * Fetch Membership Tiers Info
 */
const fetchTiersInfo = (tierIds = []) => {
  if (tierIds.length === 0) {
    return;
  }
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/membership_tier_info`, {
      filter: {
        tier_uuid: tierIds
      }
    })
  });
};

/**
 * Fetch Membership Tiers
 */
const fetchMembershipTiers = (queryParams = {}) => {
  const url = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/membership_tiers`, queryParams);
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: url
  });
};

/**
 * Fetch MDP Persons
 */
const fetchMdpPersons = (queryParams = {}) => {
  // ?term=
  const url = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/mdp_person/search`, queryParams);
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: url,
    method: 'POST'
  });
};

/**
 * Fetch Membership Filters
 */
const fetchMembershipFilters = (memberType = null) => {
  if (memberType === null) {
    return;
  }
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/membership_filters`, {
      type: memberType
    })
  });
};

/**
 * Fetch WooCommerce Products
 */
const fetchWcProducts = (queryParams = {}) => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.WC_API_V3_URL}/products`, queryParams)
  });
};

/**
 * Fetch WooCommerce Product Variations
 */
const fetchProductVariations = productId => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.WC_API_V3_URL}/products/${productId}/variations`, {
      per_page: 100,
      status: 'publish'
    })
  });
};

/***/ }),

/***/ "./node_modules/react-dom/client.js":
/*!******************************************!*\
  !*** ./node_modules/react-dom/client.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, exports, __webpack_require__) => {



var m = __webpack_require__(/*! react-dom */ "react-dom");
if (false) {} else {
  var i = m.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED;
  exports.createRoot = function(c, o) {
    i.usingClientEntryPoint = true;
    try {
      return m.createRoot(c, o);
    } finally {
      i.usingClientEntryPoint = false;
    }
  };
  exports.hydrateRoot = function(c, h, o) {
    i.usingClientEntryPoint = true;
    try {
      return m.hydrateRoot(c, h, o);
    } finally {
      i.usingClientEntryPoint = false;
    }
  };
}


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "react-dom":
/*!***************************!*\
  !*** external "ReactDOM" ***!
  \***************************/
/***/ ((module) => {

module.exports = window["ReactDOM"];

/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/url":
/*!*****************************!*\
  !*** external ["wp","url"] ***!
  \*****************************/
/***/ ((module) => {

module.exports = window["wp"]["url"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!************************************************!*\
  !*** ./src/membership_tiers/tier_cell_info.js ***!
  \************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_dom_client__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-dom/client */ "./node_modules/react-dom/client.js");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _services_api__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../services/api */ "./src/services/api.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);






const MembershipTierCellInfo = ({
  tierUuid,
  tierField
}) => {
  const [status, setStatus] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    (0,_services_api__WEBPACK_IMPORTED_MODULE_3__.fetchMembershipTiers)({
      filters: {
        id: [tierUuid]
      }
    }).then(tiers => {
      const value = tiers[0][tierField] === null ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('N/A', 'wicket-memberships') : tiers[0][tierField];
      setStatus(value);
    }).catch(error => {
      console.log('Tier Info Error:');
      console.log(error);
    });
  }, []);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, status === null && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, null), status !== null && status);
};

// init multiple instances
const app = document.querySelectorAll('.wicket_memberships_tier_cell_info');
if (app) {
  app.forEach(el => {
    (0,react_dom_client__WEBPACK_IMPORTED_MODULE_1__.createRoot)(el).render((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(MembershipTierCellInfo, {
      ...el.dataset
    }));
  });
}
})();

/******/ })()
;
//# sourceMappingURL=wicket_memberships_tier_cell_info.js.map