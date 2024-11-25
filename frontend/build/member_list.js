(()=>{"use strict";var e={5338:(e,t,a)=>{var r=a(5795);t.H=r.createRoot,r.hydrateRoot},5795:e=>{e.exports=window.ReactDOM}},t={};function a(r){var l=t[r];if(void 0!==l)return l.exports;var n=t[r]={exports:{}};return e[r](n,n.exports,a),n.exports}a.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return a.d(t,{a:t}),t},a.d=(e,t)=>{for(var r in t)a.o(t,r)&&!a.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},a.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{const e=window.React,t=window.wp.i18n;var r=a(5338);const l=window.wp.url,n=window.wp.components,s=window.wp.apiFetch;var m=a.n(s);const i="/wicket_member/v1",c=document.getElementById("member_list");c&&(0,r.H)(c).render((0,e.createElement)((({memberType:a,editMemberUrl:r})=>{const[s,c]=(0,e.useState)(!0),[o,u]=(0,e.useState)([]),[p,d]=(0,e.useState)(0),[_,h]=(0,e.useState)(0),[b,E]=(0,e.useState)(null),[g,w]=(0,e.useState)(null),[v,f]=(0,e.useState)({type:a,page:1,posts_per_page:10,status:"",order_col:"start_date",order_dir:"ASC",search:""}),[N,k]=(0,e.useState)(v);console.log(v);const y=e=>{c(!0),((e=null)=>{if(null!==e)return m()({path:(0,l.addQueryArgs)(`${i}/memberships`,e)})})(e).then((t=>{console.log(t),u(t.results),d(t.count),h(Math.ceil(t.count/e.posts_per_page)),c(!1);const a=t.results.map((e=>e.meta.membership_tier_uuid));null===b&&S(a)})).catch((e=>{console.error(e)}))},S=e=>{0!==e.length&&((e=[])=>{if(0!==e.length)return m()({path:(0,l.addQueryArgs)(`${i}/membership_tier_info`,{filter:{tier_uuid:e}})})})(e).then((e=>{E(e)})).catch((e=>{console.log("Tiers Info Error:"),console.log(e)}))},x=e=>null===b?null:b.hasOwnProperty("tier_data")&&b.tier_data.hasOwnProperty(e)?b.tier_data[e]:null;return(0,e.useEffect)((()=>{((e=null)=>{if(null!==e)return m()({path:(0,l.addQueryArgs)(`${i}/membership_filters`,{type:e})})})(a).then((e=>{w(e)})).catch((e=>{console.error(e)})),y(v)}),[]),(0,e.createElement)(e.Fragment,null,(0,e.createElement)("div",{className:"wrap"},(0,e.createElement)("h1",{className:"wp-heading-inline"},"individual"===a?(0,t.__)("Individual Members","wicket-memberships"):(0,t.__)("Organization Members","wicket-memberships")),(0,e.createElement)("hr",{className:"wp-header-end"}),(0,e.createElement)("form",{onSubmit:e=>{e.preventDefault();const t={...v,search:N.search};f(t),y(t)}},(0,e.createElement)("p",{className:"search-box"},(0,e.createElement)("label",{className:"screen-reader-text",htmlFor:"post-search-input"},(0,t.__)("Search Member","wicket-memberships")),(0,e.createElement)("input",{type:"search",id:"post-search-input",value:N.search,onChange:e=>k({...N,search:e.target.value})}),(0,e.createElement)("input",{type:"submit",className:"button",value:(0,t.__)("Search Member","wicket-memberships")}))),(0,e.createElement)("div",{className:"tablenav top"},(0,e.createElement)("form",{onSubmit:e=>{e.preventDefault();const t={...v,filter:{membership_status:N.filter.membership_status,membership_tier:N.filter.membership_tier}};""===t.filter.membership_status&&delete t.filter.membership_status,""===t.filter.membership_tier&&delete t.filter.membership_tier,f(t),y(t)}},(0,e.createElement)("div",{className:"alignleft actions"},(0,e.createElement)("select",{name:"filter_status",id:"filter_status",onChange:e=>{k({...N,filter:{...N.filter,membership_status:e.target.value}})}},(0,e.createElement)("option",{value:""},(0,t.__)("Status","wicket-memberships")),null!==g&&g.membership_status.map(((t,a)=>(0,e.createElement)("option",{key:a,value:t.name},t.value)))),(0,e.createElement)("select",{name:"filter_tier",id:"filter_tier",onChange:e=>{k({...N,filter:{...N.filter,membership_tier:e.target.value}})}},(0,e.createElement)("option",{value:""},(0,t.__)("All Tiers","wicket-memberships")),null!==g&&g.tiers.map(((t,a)=>null!==x(t.value)&&(0,e.createElement)("option",{key:a,value:t.value},x(t.value).name)))),(0,e.createElement)("input",{type:"submit",id:"post-query-submit",className:"button",value:(0,t.__)("Filter","wicket-memberships")})))),(0,e.createElement)("table",{className:"wp-list-table widefat fixed striped table-view-list posts"},(0,e.createElement)("thead",null,(0,e.createElement)("tr",null,"organization"===a&&(0,e.createElement)(e.Fragment,null,(0,e.createElement)("th",{scope:"col",className:"manage-column"},(0,t.__)("Organization Name","wicket-memberships")),(0,e.createElement)("th",{scope:"col",className:"manage-column"},(0,t.__)("Location","wicket-memberships"))),(0,e.createElement)("th",{scope:"col",className:"manage-column"},"individual"===a?(0,t.__)("Individual Member Name","wicket-memberships"):(0,t.__)("Contact","wicket-memberships")),(0,e.createElement)("th",{scope:"col",className:"manage-column"},(0,t.__)("Status","wicket-memberships")),(0,e.createElement)("th",{scope:"col",className:"manage-column"},(0,t.__)("Tier","wicket-memberships")),(0,e.createElement)("th",{scope:"col",className:"manage-column"},(0,t.__)("Link to MDP","wicket-memberships")))),(0,e.createElement)("tbody",null,s&&(0,e.createElement)("tr",{className:"alternate"},(0,e.createElement)("td",{className:"column-columnname",colSpan:"organization"===a?6:4},(0,e.createElement)(n.Spinner,null))),!s&&0===o.length&&(0,e.createElement)("tr",{className:"alternate"},(0,e.createElement)("td",{className:"column-columnname",colSpan:4},(0,t.__)("No members found.","wicket-memberships"))),!s&&o.length>0&&o.map(((s,m)=>(0,e.createElement)("tr",{key:m},"organization"===a&&(0,e.createElement)(e.Fragment,null,(0,e.createElement)("td",null,(0,e.createElement)("strong",null,(0,e.createElement)("a",{href:(0,l.addQueryArgs)(r,{id:s.meta.org_uuid}),className:"row-title"},s.meta.org_name)),(0,e.createElement)("div",{className:"row-actions"},(0,e.createElement)("span",{className:"edit"},(0,e.createElement)("a",{href:(0,l.addQueryArgs)(r,{id:s.meta.org_uuid}),"aria-label":(0,t.__)("Edit","wicket-memberships")},(0,t.__)("Edit","wicket-memberships"))))),(0,e.createElement)("td",null,s.meta.org_location)),(0,e.createElement)("td",null,"individual"===a&&(0,e.createElement)(e.Fragment,null,(0,e.createElement)("strong",null,(0,e.createElement)("a",{href:(0,l.addQueryArgs)(r,{id:s.user.user_login}),className:"row-title"},s.user.display_name)),(0,e.createElement)("div",{className:"row-actions"},(0,e.createElement)("span",{className:"edit"},(0,e.createElement)("a",{href:(0,l.addQueryArgs)(r,{id:s.user.user_login}),"aria-label":(0,t.__)("Edit","wicket-memberships")},(0,t.__)("Edit","wicket-memberships"))))),"organization"===a&&(0,e.createElement)(e.Fragment,null,s.user.display_name)),(0,e.createElement)("td",null,(0,e.createElement)("span",{style:{color:"active"===s.meta.membership_status?"green":"",textTransform:"capitalize"}},s.meta.membership_status)),(0,e.createElement)("td",null,null===b&&(0,e.createElement)(n.Spinner,null),null!==x(s.meta.membership_tier_uuid)&&x(s.meta.membership_tier_uuid).name),(0,e.createElement)("td",null,(0,e.createElement)("a",{target:"_blank",href:s.user.mdp_link},(0,t.__)("View","wicket-memberships")," ",(0,e.createElement)(n.Icon,{icon:"external"})))))))),(0,e.createElement)("div",{className:"tablenav bottom"},(0,e.createElement)("div",{className:"tablenav-pages"},(0,e.createElement)("span",{className:"displaying-num"},p," ",(0,t.__)("items","wicket-memberships")),_>1&&(0,e.createElement)("span",{className:"pagination-links"},(0,e.createElement)("button",{className:"prev-page button",disabled:1===v.page,onClick:()=>{const e={...v,page:v.page-1};f(e),y(e)}},"‹"),(0,e.createElement)("span",{className:"screen-reader-text"},(0,t.__)("Current Page","wicket-memberships")),(0,e.createElement)("span",{id:"table-paging",className:"paging-input"}," ",(0,e.createElement)("span",{className:"tablenav-paging-text"},v.page," ",(0,t.__)("of","wicket-memberships")," ",(0,e.createElement)("span",{className:"total-pages"},_))," "),(0,e.createElement)("button",{className:"next-page button",disabled:v.page===_,onClick:()=>{const e={...v,page:v.page+1};f(e),y(e)}},"›"))),(0,e.createElement)("br",{className:"clear"}))))}),{...c.dataset}))})()})();