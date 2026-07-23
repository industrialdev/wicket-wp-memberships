import { __ } from "@wordpress/i18n";
import { Icon, __experimentalHeading as Heading } from "@wordpress/components";
import WicketButton from "./WicketButton";
import { FormFlex } from "../styled_elements";
import { formatDateWithTooltip } from "../constants";

const CalendarSeasonsTable = ({ seasons, disabled, onEditSeason }) => (
  <>
    <FormFlex>
      <Heading level="4" weight="300">
        {__("Seasons", "wicket-memberships")}
      </Heading>
    </FormFlex>
    <FormFlex>
      <table cellSpacing="0" className="widefat">
        <thead>
          <tr>
            <th className="manage-column column-columnname" scope="col">
              {__("Season Name", "wicket-memberships")}
            </th>
            <th className="manage-column column-columnname" scope="col">
              {__("Status", "wicket-memberships")}
            </th>
            <th className="manage-column column-columnname" scope="col">
              {__("Start Date", "wicket-memberships")}
            </th>
            <th className="manage-column column-columnname" scope="col">
              {__("End Date", "wicket-memberships")}
            </th>
            <th className="check-column"></th>
          </tr>
        </thead>
        <tbody>
          {seasons.map((season, index) => (
            <tr
              className={index % 2 === 0 ? "alternate" : ""}
              key={`${season.season_name}-${index}`}
            >
              <td className="column-columnname">{season.season_name}</td>
              <td className="column-columnname">
                {season.active
                  ? __("Active", "wicket-memberships")
                  : __("Inactive", "wicket-memberships")}
              </td>
              <td className="column-columnname">{formatDateWithTooltip(season.start_date)}</td>
              <td className="column-columnname">{formatDateWithTooltip(season.end_date)}</td>
              <td>
                <WicketButton
                  disabled={disabled}
                  onClick={() => onEditSeason(index)}
                >
                  <Icon icon="edit" />
                </WicketButton>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </FormFlex>
  </>
);

export default CalendarSeasonsTable;
