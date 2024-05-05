import { __ } from '@wordpress/i18n';
import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { PLUGIN_API_URL } from '../constants';
import { Wrap, ActionRow, FormFlex, ErrorsRow, BorderedBox, SelectWpStyled, CustomDisabled, LabelWpStyled } from '../styled_elements';
import { TextControl, Spinner, Button, Flex, FlexItem, Modal, TextareaControl, FlexBlock, Notice, SelectControl, CheckboxControl, Disabled, __experimentalHeading as Heading, Icon } from '@wordpress/components';
import styled from 'styled-components';

export const EditWrap = styled.div`
	max-width: 1000px;
`;

const MarginedFlex = styled(Flex)`
	margin-top: 15px;
`;

const WhiteBorderedBox = styled(BorderedBox)`
  background: #fff;
`;

export const RecordTopInfo = styled.div`
  background: #F0F6FC;
  margin-top: 15px;
  padding: 15px;
  font-size: 14px;
`;

const MemberEdit = ({ memberType, recordId }) => {

  const [isLoading, setIsLoading] = useState(true);

  const [member, setMember] = useState(null);
  const [memberships, setMemberships] = useState(null);

  const fetchMemberships = async () => {
    setIsLoading(true);
    apiFetch({
      path: addQueryArgs(`${PLUGIN_API_URL}/membership_entity`, { entity_id: recordId }),
    }).then((response) => {
      console.log(response);

      setMemberships(response);
      setIsLoading(false);
    }).catch((error) => {
      console.error(error);
    });
  }

  useEffect(() => {
    fetchMemberships();
  }, []);

	return (
		<>
			<div className="wrap" >
				<h1 className="wp-heading-inline">
					{memberType === 'individual' ? __('Individual Members', 'wicket-memberships') : __('Organization Members', 'wicket-memberships')}
				</h1>
				<hr className="wp-header-end"></hr>

        <EditWrap>
          <WhiteBorderedBox>
            <Flex
              align='end'
              justify='start'
              gap={5}
              direction={[
                'column',
                'row'
              ]}
            >
              <FlexBlock>
                <Heading
                  level={3}
                >%NAME%</Heading>
              </FlexBlock>
              <FlexItem>
                <Button
                  variant='primary'
                >
                  <Icon icon='external' />&nbsp;
                  {__('View in MDP', 'wicket-memberships')}
                </Button>
              </FlexItem>
            </Flex>
            <RecordTopInfo>
              <Flex
                align='end'
                justify='start'
                gap={10}
                direction={[
                  'column',
                  'row'
                ]}
              >
                <FlexItem>
                  <strong>{__('Email:', 'wicket-memberships')}</strong> %EMAIL%
                </FlexItem>
                <FlexItem>
                  <strong>{__('Identifying Number:', 'wicket-memberships')}</strong> %ID%
                </FlexItem>
              </Flex>
              <MarginedFlex
                align='end'
                justify='start'
                gap={10}
                direction={[
                  'column',
                  'row'
                ]}
              >
                <FlexItem>
                  <strong>{__('Location:', 'wicket-memberships')}</strong> %LOCATION%
                </FlexItem>
                <FlexItem>
                  <strong>{__('Season:', 'wicket-memberships')}</strong> %SEASON%
                </FlexItem>
                <FlexItem>
                  <strong>{__('Primary Contact:', 'wicket-memberships')}</strong> %CONTACT%
                </FlexItem>
              </MarginedFlex>
            </RecordTopInfo>
          </WhiteBorderedBox>
        </EditWrap>

			</div>
		</>
	);
};

const app = document.getElementById('edit_member');
if (app) {
	createRoot(app).render(<MemberEdit {...app.dataset} />);
}