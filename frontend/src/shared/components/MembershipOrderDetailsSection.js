import { __ } from "@wordpress/i18n";
import { MembershipTable } from "../styled_elements";
import { formatDateWithTooltip, formatCurrency } from "../constants";

/**
 * MembershipOrderDetailsSection — order record table rendered inside an
 * expanded membership record row.
 *
 * Mirrors the billing_table from members/edit.js. Displays order number
 * (linked), order date, total, and status.
 *
 * Data-agnostic: receives only flat props. Callers are responsible for mapping
 * their page-specific data shapes to these props.
 *
 * @param {Array}  props.orders  - Array of order objects. Each must include:
 *                                 id, link, date_created, total, status.
 */
const MembershipOrderDetailsSection = ({ orders = [] }) => {
  return (
    <MembershipTable>
      <table className="widefat billing_table" cellSpacing="0">
        <thead>
          <tr>
            <th className="manage-column column-columnname" scope="col">
              {__("Order Number", "wicket-memberships")}
            </th>
            <th className="manage-column column-columnname" scope="col">
              {__("Order Date", "wicket-memberships")}
            </th>
            <th className="manage-column column-columnname" scope="col">
              {__("Order Total", "wicket-memberships")}
            </th>
            <th className="manage-column column-columnname" scope="col">
              {__("Order Status", "wicket-memberships")}
            </th>
          </tr>
        </thead>
        <tbody>
          {orders.map((order, index) => (
            <tr key={order.id ?? index}>
              <td className="column-columnname">
                {order.link ? (
                  <a target="_blank" href={order.link} rel="noreferrer">
                    #{order.id}
                  </a>
                ) : (
                  `#${order.id}`
                )}
              </td>
              <td className="column-columnname">{formatDateWithTooltip(order.date_created)}</td>
              <td className="column-columnname">{formatCurrency(order.total)}</td>
              <td className="column-columnname">{order.status}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </MembershipTable>
  );
};

export default MembershipOrderDetailsSection;
