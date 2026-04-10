import { Component } from "react";
import { __ } from "@wordpress/i18n";
import { Notice } from "@wordpress/components";
import WicketButton from "./WicketButton";
import { ErrorsRow } from "../styled_elements";

class AdminPageErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = {
      hasError: false,
    };

    this.handleReset = this.handleReset.bind(this);
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidUpdate(prevProps) {
    if (this.state.hasError && prevProps.resetKey !== this.props.resetKey) {
      this.setState({ hasError: false });
    }
  }

  handleReset() {
    if (this.props.onReset) {
      this.props.onReset();
    }
  }

  render() {
    if (this.state.hasError) {
      return (
        <ErrorsRow>
          <Notice isDismissible={false} status="error">
            <div>
              {__(
                "This page hit an unexpected error and could not finish rendering.",
                "wicket-memberships",
              )}
            </div>
            <div>
              <WicketButton onClick={this.handleReset} variant="link">
                {__("Try again", "wicket-memberships")}
              </WicketButton>
            </div>
          </Notice>
        </ErrorsRow>
      );
    }

    return this.props.children;
  }
}

export default AdminPageErrorBoundary;
