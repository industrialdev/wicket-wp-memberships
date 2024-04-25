import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { Spinner, Flex, __experimentalHeading as Heading } from '@wordpress/components';
import { MDP_API_URL } from '../constants';
import { FormFlex, BorderedBox } from '../styled_elements';

const MembershipConfigTiers = ({ configPostId, tierCptSlug, tierMdpUuids }) => {

  if ( ! configPostId ) {
    return null;
  }

  console.log(tierMdpUuids);

  const [mdpTiers, setMdpTiers] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {

    // tierMdpUuids to array
    const tierIdsArray = tierMdpUuids.split(',');
    console.log(tierIdsArray);

		apiFetch({ path: addQueryArgs(`${MDP_API_URL}/membership_tiers`, {
      filter: {
        id: tierIdsArray
      }
    }) }).then((tiers) => {
      console.log('tiers.js');
      console.log(tiers);

      // TODO: Remove this when the API is updated to return the certain tiers
      tiers = tiers.filter((tier) => {
        return tierMdpUuids.includes(tier.uuid);
      });

      tiers = tiers.map((tier) => {
        return {
          uuid: tier.uuid,
          name: tier.name,
          active: tier.status === 'Active' ? true : false,
          type: tier.type, // orgranization, individual
          grace_period_days: 0, // TODO: Update when grace period is added to MDP
          category: '', // TODO: Update here
        }
      })

      setMdpTiers(tiers);
      setIsLoading(false);
		}).catch((error) => {

      console.log('Tiers Error:');
      console.log(error);
			setIsLoading(false);
		});

  }, []);

	return (
		<BorderedBox>
      <Flex>
        <Heading level='4' weight='300' >
          {__('Connected Tiers', 'wicket-memberships')}
        </Heading>
      </Flex>
      <FormFlex>
        <table className="widefat" cellSpacing="0">
          <thead>
            <tr>
              <th className="manage-column column-columnname" scope="col">
                {__('Membership Tier', 'wicket-memberships')}
              </th>
              <th className="manage-column column-columnname" scope="col">
                {__('Status', 'wicket-memberships')}
              </th>
              <th className="manage-column column-columnname" scope="col">
                {__('Type', 'wicket-memberships')}
              </th>
              <th className="manage-column column-columnname" scope="col">
                {__('Category', 'wicket-memberships')}
              </th>
              <th className="manage-column column-columnname" scope="col">
                {__('# Members', 'wicket-memberships')}
              </th>
            </tr>
          </thead>
          <tbody>
            {isLoading && (
              <tr className="alternate">
                <td className="column-columnname" colSpan={5}>
                  <Spinner />
                </td>
              </tr>
            )}
            {mdpTiers.map((mdpTier, index) => (
                <tr key={index} className={index % 2 === 0 ? 'alternate' : ''}>
                  <td className="column-columnname">
                    {mdpTier.name}
                  </td>
                  <td className="column-columnname">
                    {mdpTier.status === 'Active' ? __('Active') : __('Inactive')}
                  </td>
                  <td className="column-columnname">
                    {mdpTier.type}
                  </td>
                  <td className="column-columnname">
                    {mdpTier.category.length > 0 ? mdpTier.category : __('N/A', 'wicket-memberships')}
                  </td>
                  <td className="column-columnname">
                    -
                  </td>
                </tr>
              )
            )}
          </tbody>
        </table>
      </FormFlex>
		</BorderedBox>
	);
};

export default MembershipConfigTiers;