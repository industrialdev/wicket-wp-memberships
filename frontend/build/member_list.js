/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/shared/constants.js":
/*!*********************************!*\
  !*** ./src/shared/constants.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   API_URL: () => (/* binding */ API_URL),
/* harmony export */   DEFAULT_DATE_FORMAT: () => (/* binding */ DEFAULT_DATE_FORMAT),
/* harmony export */   PLUGIN_API_URL: () => (/* binding */ PLUGIN_API_URL),
/* harmony export */   PLUGIN_SETTINGS: () => (/* binding */ PLUGIN_SETTINGS),
/* harmony export */   TIER_CPT_SLUG: () => (/* binding */ TIER_CPT_SLUG),
/* harmony export */   WC_API_V3_URL: () => (/* binding */ WC_API_V3_URL),
/* harmony export */   WC_PRODUCT_TYPES: () => (/* binding */ WC_PRODUCT_TYPES),
/* harmony export */   WP_ADMIN_URL: () => (/* binding */ WP_ADMIN_URL)
/* harmony export */ });
const API_URL = "/wp/v2";
const WC_API_V3_URL = "/wc/v3";
const PLUGIN_API_URL = "/wicket_member/v1";
const TIER_CPT_SLUG = "wicket_mship_tier";
const DEFAULT_DATE_FORMAT = "yyyy-MM-dd";
const WC_PRODUCT_TYPES = ["subscription", "variable-subscription"];
const PLUGIN_SETTINGS = wicketMembershipsSettings;
const WP_ADMIN_URL = wicketMembershipsSettings.adminUrl;

/***/ }),

/***/ "./src/shared/services/api.js":
/*!************************************!*\
  !*** ./src/shared/services/api.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createRenewalOrder: () => (/* binding */ createRenewalOrder),
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
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../constants */ "./src/shared/constants.js");




/**
 * Fetch Local Membership Tiers Posts
 */
const fetchTiers = () => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.API_URL}/${_constants__WEBPACK_IMPORTED_MODULE_2__.TIER_CPT_SLUG}`, {
      status: "publish",
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
    method: "POST",
    data: data
  });
};

/**
 * Update Membership Status
 */
const updateMembershipStatus = (membershipId, status) => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: `${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/admin/manage_status`,
    method: "POST",
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
    method: "POST"
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
const fetchProductVariations = (productId, queryParams = {}) => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_1__.addQueryArgs)(`${_constants__WEBPACK_IMPORTED_MODULE_2__.WC_API_V3_URL}/products/${productId}/variations`, queryParams)
  });
};

/**
 * Create Renewal Order
 */
const createRenewalOrder = (membershipId, productId, variationId) => {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: `${_constants__WEBPACK_IMPORTED_MODULE_2__.PLUGIN_API_URL}/membership/${membershipId}/create_renewal_order`,
    method: "POST",
    data: {
      membership_post_id: membershipId,
      product_id: productId,
      variation_id: variationId
    }
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
/*!******************************!*\
  !*** ./src/members/index.js ***!
  \******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_dom_client__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react-dom/client */ "./node_modules/react-dom/client.js");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _shared_services_api__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../shared/services/api */ "./src/shared/services/api.js");







const SortableHeader = ({
  label,
  col,
  currentCol,
  currentDir,
  onSort
}) => {
  const isActive = currentCol === col;
  const className = `manage-column sortable ${isActive ? `sorted ${currentDir === 'ASC' ? 'asc' : 'desc'}` : ''}`;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("th", {
    scope: "col",
    className: className
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      onSort(col);
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "sorting-indicators"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "sorting-indicator asc",
    "aria-hidden": "true"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "sorting-indicator desc",
    "aria-hidden": "true"
  }))));
};
const MemberList = ({
  memberType,
  editMemberUrl
}) => {
  const [isLoading, setIsLoading] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [members, setMembers] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [totalMembers, setTotalMembers] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(0);
  const [totalPages, setTotalPages] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(0);
  const [tiersInfo, setTiersInfo] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [membershipFilters, setMembershipFilters] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [activeTab, setActiveTab] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('all');
  const [tabCounts, setTabCounts] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)({
    all: 0,
    pending: 0,
    grace_period: 0
  });
  const [searchParams, setSearchParams] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)({
    type: memberType,
    page: 1,
    posts_per_page: 10,
    status: "",
    order_col: "post_modified",
    order_dir: "DESC",
    // filter: {
    //   membership_status: '',
    //   membership_tier: '',
    // },
    search: ""
  });
  const [tempSearchParams, setTempSearchParams] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(searchParams);

  // console.log(tempSearchParams);
  console.log(searchParams);
  const getMembers = params => {
    setIsLoading(true);
    (0,_shared_services_api__WEBPACK_IMPORTED_MODULE_5__.fetchMembers)(params).then(response => {
      console.log(response);
      setMembers(response.results);
      setTotalMembers(response.count);
      setTotalPages(Math.ceil(response.count / params.posts_per_page));
      setIsLoading(false);
      const tierIds = [...new Set(response.results.flatMap(member => (member.user.all_membership_tiers || [{
        uuid: member.meta.membership_tier_uuid
      }]).map(t => t.uuid)))];
      if (tiersInfo === null) {
        getTiersInfo(tierIds);
      }
    }).catch(error => {
      console.error(error);
    });
  };
  const getTiersInfo = tierIds => {
    if (tierIds.length === 0) {
      return;
    }
    (0,_shared_services_api__WEBPACK_IMPORTED_MODULE_5__.fetchTiersInfo)(tierIds).then(tiersInfo => {
      setTiersInfo(tiersInfo);
    }).catch(error => {
      console.log("Tiers Info Error:");
      console.log(error);
    });
  };
  const getMembershipFilters = () => {
    (0,_shared_services_api__WEBPACK_IMPORTED_MODULE_5__.fetchMembershipFilters)(memberType).then(filters => {
      setMembershipFilters(filters);
    }).catch(error => {
      console.error(error);
    });
  };
  const getTabCounts = () => {
    (0,_shared_services_api__WEBPACK_IMPORTED_MODULE_5__.fetchMembers)({
      type: memberType,
      page: 1,
      posts_per_page: 1
    }).then(r => setTabCounts(prev => ({
      ...prev,
      all: r.count
    }))).catch(console.error);
    (0,_shared_services_api__WEBPACK_IMPORTED_MODULE_5__.fetchMembers)({
      type: memberType,
      page: 1,
      posts_per_page: 1,
      filter: {
        membership_status: 'pending'
      }
    }).then(r => setTabCounts(prev => ({
      ...prev,
      pending: r.count
    }))).catch(console.error);
    (0,_shared_services_api__WEBPACK_IMPORTED_MODULE_5__.fetchMembers)({
      type: memberType,
      page: 1,
      posts_per_page: 1,
      filter: {
        membership_status: 'grace_period'
      }
    }).then(r => setTabCounts(prev => ({
      ...prev,
      grace_period: r.count
    }))).catch(console.error);
  };
  const handleSort = col => {
    const newDir = searchParams.order_col === col && searchParams.order_dir === 'ASC' ? 'DESC' : 'ASC';
    const newSearchParams = {
      ...searchParams,
      order_col: col,
      order_dir: newDir,
      page: 1
    };
    setSearchParams(newSearchParams);
    getMembers(newSearchParams);
  };
  const handleTabClick = tab => {
    setActiveTab(tab);
    const newSearchParams = {
      ...searchParams,
      page: 1
    };
    if (tab === 'all') {
      delete newSearchParams.filter;
    } else {
      newSearchParams.filter = {
        membership_status: tab
      };
    }
    setSearchParams(newSearchParams);
    getMembers(newSearchParams);
  };
  const getTierInfo = tierId => {
    if (tiersInfo === null) {
      return null;
    }
    if (!tiersInfo.hasOwnProperty("tier_data") || !tiersInfo.tier_data.hasOwnProperty(tierId)) {
      return null;
    }
    return tiersInfo.tier_data[tierId];
  };
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    // https://localhost/wp-json/wicket_member/v1/memberships?order_col=start_date&order_dir=ASC&type=individual
    // https://localhost/wp-json/wicket_member/v1/memberships?order_col=start_date&order_dir=ASC&filter[membership_status]=expired&filter[membership_tier]=88d6a08a-ab3c-4f01-93d7-ddf07995ab25&search=Veterinary&type=individual
    getMembershipFilters();
    getMembers(searchParams);
    getTabCounts();
  }, []);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "wrap"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h1", {
    className: "wp-heading-inline"
  }, memberType === "individual" ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Individual Members", "wicket-memberships") : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Organization Members", "wicket-memberships")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("hr", {
    className: "wp-header-end"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", {
    className: "subsubsub"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
    className: "all"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      handleTabClick('all');
    },
    className: activeTab === 'all' ? 'current' : '',
    "aria-current": activeTab === 'all' ? 'page' : undefined
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('All', 'wicket-memberships'), " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "count"
  }, "(", tabCounts.all, ")")), " |"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
    className: "pending"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      handleTabClick('pending');
    },
    className: activeTab === 'pending' ? 'current' : '',
    "aria-current": activeTab === 'pending' ? 'page' : undefined
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Pending', 'wicket-memberships'), " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "count"
  }, "(", tabCounts.pending, ")")), " |"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
    className: "grace_period"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      handleTabClick('grace_period');
    },
    className: activeTab === 'grace_period' ? 'current' : '',
    "aria-current": activeTab === 'grace_period' ? 'page' : undefined
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Grace Period', 'wicket-memberships'), " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "count"
  }, "(", tabCounts.grace_period, ")")))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("form", {
    onSubmit: e => {
      e.preventDefault();
      const newSearchParams = {
        ...searchParams,
        search: tempSearchParams.search,
        page: 1
      };
      setSearchParams(newSearchParams);
      getMembers(newSearchParams);
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "search-box"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    className: "screen-reader-text",
    htmlFor: "post-search-input"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Search Member", "wicket-memberships")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "search",
    id: "post-search-input",
    value: tempSearchParams.search,
    onChange: e => setTempSearchParams({
      ...tempSearchParams,
      search: e.target.value
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "submit",
    className: "button",
    value: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Search Member", "wicket-memberships")
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "tablenav top"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("form", {
    onSubmit: e => {
      e.preventDefault();
      const newSearchParams = {
        ...searchParams,
        filter: {
          membership_status: tempSearchParams.filter.membership_status,
          membership_tier: tempSearchParams.filter.membership_tier
        }
      };
      // remove if empty filter values
      if (newSearchParams.filter.membership_status === "") {
        delete newSearchParams.filter.membership_status;
      }
      if (newSearchParams.filter.membership_tier === "") {
        delete newSearchParams.filter.membership_tier;
      }
      setSearchParams(newSearchParams);
      getMembers(newSearchParams);
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "alignleft actions"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("select", {
    name: "filter_status",
    id: "filter_status",
    onChange: e => {
      setTempSearchParams({
        ...tempSearchParams,
        filter: {
          ...tempSearchParams.filter,
          membership_status: e.target.value
        }
      });
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("option", {
    value: ""
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Status", "wicket-memberships")), membershipFilters !== null && membershipFilters.membership_status.map((status, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("option", {
    key: index,
    value: status.name
  }, status.value))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("select", {
    name: "filter_tier",
    id: "filter_tier",
    onChange: e => {
      setTempSearchParams({
        ...tempSearchParams,
        filter: {
          ...tempSearchParams.filter,
          membership_tier: e.target.value
        }
      });
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("option", {
    value: ""
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("All Tiers", "wicket-memberships")), membershipFilters !== null && membershipFilters.tiers.map((tier, index) => getTierInfo(tier.value) !== null && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("option", {
    key: index,
    value: tier.value
  }, getTierInfo(tier.value).name))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "submit",
    id: "post-query-submit",
    className: "button",
    value: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Filter", "wicket-memberships")
  })))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("table", {
    className: "wp-list-table widefat fixed striped table-view-list posts"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("thead", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("tr", null, memberType === "organization" && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SortableHeader, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Organization Name', 'wicket-memberships'),
    col: "org_name",
    currentCol: searchParams.order_col,
    currentDir: searchParams.order_dir,
    onSort: handleSort
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SortableHeader, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Location', 'wicket-memberships'),
    col: "org_location",
    currentCol: searchParams.order_col,
    currentDir: searchParams.order_dir,
    onSort: handleSort
  })), memberType === 'individual' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SortableHeader, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('First Name', 'wicket-memberships'),
    col: "user_name",
    currentCol: searchParams.order_col,
    currentDir: searchParams.order_dir,
    onSort: handleSort
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SortableHeader, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Last Name', 'wicket-memberships'),
    col: "user_last_name",
    currentCol: searchParams.order_col,
    currentDir: searchParams.order_dir,
    onSort: handleSort
  })), memberType === 'organization' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SortableHeader, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Contact', 'wicket-memberships'),
    col: "user_name",
    currentCol: searchParams.order_col,
    currentDir: searchParams.order_dir,
    onSort: handleSort
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SortableHeader, {
    label: memberType === 'individual' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Email', 'wicket-memberships') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Contact Email', 'wicket-memberships'),
    col: "user_email",
    currentCol: searchParams.order_col,
    currentDir: searchParams.order_dir,
    onSort: handleSort
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(SortableHeader, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Last Updated', 'wicket-memberships'),
    col: "post_modified",
    currentCol: searchParams.order_col,
    currentDir: searchParams.order_dir,
    onSort: handleSort
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("th", {
    scope: "col",
    className: "manage-column"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Tier(s)', 'wicket-memberships')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("th", {
    scope: "col",
    className: "manage-column"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Link to MDP', 'wicket-memberships')))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("tbody", null, isLoading && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("tr", {
    className: "alternate"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", {
    className: "column-columnname",
    colSpan: memberType === 'organization' ? 7 : 6
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Spinner, null))), !isLoading && members.length === 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("tr", {
    className: "alternate"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", {
    className: "column-columnname",
    colSpan: memberType === 'organization' ? 7 : 5
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No members found.', 'wicket-memberships'))), !isLoading && members.length > 0 && members.map((member, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("tr", {
    key: index
  }, memberType === "organization" && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.addQueryArgs)(editMemberUrl, {
      id: member.meta.org_uuid
    }),
    className: "row-title"
  }, member.meta.org_name)), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "row-actions"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "edit"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.addQueryArgs)(editMemberUrl, {
      id: member.meta.org_uuid
    }),
    "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Edit", "wicket-memberships")
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Edit", "wicket-memberships"))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, member.meta.org_location)), memberType === 'individual' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.addQueryArgs)(editMemberUrl, {
      id: member.user.user_login
    }),
    className: "row-title"
  }, member.user.first_name)), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "row-actions"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "edit"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.addQueryArgs)(editMemberUrl, {
      id: member.user.user_login
    }),
    "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Edit", "wicket-memberships")
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Edit", "wicket-memberships"))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.addQueryArgs)(editMemberUrl, {
      id: member.user.user_login
    }),
    className: "row-title"
  }, member.user.last_name)), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "row-actions"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "edit"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.addQueryArgs)(editMemberUrl, {
      id: member.user.user_login
    }),
    "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Edit", "wicket-memberships")
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Edit", "wicket-memberships")))))), memberType === 'organization' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, member.user.display_name), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, member.user.user_email), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, member.post_modified ? moment(member.post_modified).format('MMMM D, YYYY') : '-'), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, tiersInfo === null && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Spinner, null), tiersInfo !== null && (() => {
    const ACTIVE_STATUSES = ['active', 'delayed', 'grace_period', 'pending'];
    const STATUS_LABELS = {
      active: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Active', 'wicket-memberships'),
      delayed: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Delayed', 'wicket-memberships'),
      grace_period: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Grace Period', 'wicket-memberships'),
      pending: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Pending', 'wicket-memberships')
    };
    const allTiers = member.user.all_membership_tiers || [{
      uuid: member.meta.membership_tier_uuid,
      status: member.meta.membership_status
    }];
    const activeTiers = allTiers.filter(t => ACTIVE_STATUSES.includes(t.status));
    if (activeTiers.length === 0) {
      return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Inactive', 'wicket-memberships'));
    }
    return activeTiers.map((t, i) => {
      const info = getTierInfo(t.uuid);
      const name = info ? info.name : t.uuid;
      const statusLabel = STATUS_LABELS[t.status] || t.status;
      return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
        key: i
      }, i > 0 && ', ', name, " (", statusLabel, ")");
    });
  })()), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("td", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    target: "_blank",
    href: member.user.mdp_link
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("View", "wicket-memberships"), "\xA0", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Icon, {
    icon: "external"
  }))))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "tablenav bottom"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "tablenav-pages"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "displaying-num"
  }, totalMembers, " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("items", "wicket-memberships")), totalPages > 1 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "pagination-links"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    className: "prev-page button",
    disabled: searchParams.page === 1,
    onClick: () => {
      const newSearchParams = {
        ...searchParams,
        page: searchParams.page - 1
      };
      setSearchParams(newSearchParams);
      getMembers(newSearchParams);
    }
  }, "\u2039"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "screen-reader-text"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Current Page", "wicket-memberships")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    id: "table-paging",
    className: "paging-input"
  }, "\xA0", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "tablenav-paging-text"
  }, searchParams.page, " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("of", "wicket-memberships"), " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "total-pages"
  }, totalPages)), "\xA0"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    className: "next-page button",
    disabled: searchParams.page === totalPages,
    onClick: () => {
      const newSearchParams = {
        ...searchParams,
        page: searchParams.page + 1
      };
      setSearchParams(newSearchParams);
      getMembers(newSearchParams);
    }
  }, "\u203A"))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("br", {
    className: "clear"
  }))));
};
const app = document.getElementById("member_list");
if (app) {
  (0,react_dom_client__WEBPACK_IMPORTED_MODULE_2__.createRoot)(app).render((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(MemberList, {
    ...app.dataset
  }));
}
})();

/******/ })()
;
//# sourceMappingURL=member_list.js.map