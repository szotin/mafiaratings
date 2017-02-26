namespace MafiaRatings
{
    partial class SelectUserControl
    {
        /// <summary> 
        /// Required designer variable.
        /// </summary>
        private System.ComponentModel.IContainer components = null;

        /// <summary> 
        /// Clean up any resources being used.
        /// </summary>
        /// <param name="disposing">true if managed resources should be disposed; otherwise, false.</param>
        protected override void Dispose(bool disposing)
        {
            if (disposing && (components != null))
            {
                components.Dispose();
            }
            base.Dispose(disposing);
        }

        #region Component Designer generated code

        /// <summary> 
        /// Required method for Designer support - do not modify 
        /// the contents of this method with the code editor.
        /// </summary>
        private void InitializeComponent()
        {
            this.components = new System.ComponentModel.Container();
            System.Windows.Forms.Label label1;
            System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(SelectUserControl));
            this.userListView = new System.Windows.Forms.ListView();
            this.userNameTextBox = new System.Windows.Forms.TextBox();
            this.toolTip = new System.Windows.Forms.ToolTip(this.components);
            this.otherClubCheckBox = new System.Windows.Forms.CheckBox();
            label1 = new System.Windows.Forms.Label();
            this.SuspendLayout();
            // 
            // label1
            // 
            resources.ApplyResources(label1, "label1");
            label1.ImageKey = global::MafiaRatings.Properties.Resources.String1;
            label1.Name = "label1";
            this.toolTip.SetToolTip(label1, global::MafiaRatings.Properties.Resources.String1);
            // 
            // userListView
            // 
            resources.ApplyResources(this.userListView, "userListView");
            this.userListView.Activation = System.Windows.Forms.ItemActivation.OneClick;
            this.userListView.HideSelection = false;
            this.userListView.HoverSelection = true;
            this.userListView.MultiSelect = false;
            this.userListView.Name = "userListView";
            this.userListView.Sorting = System.Windows.Forms.SortOrder.Ascending;
            this.toolTip.SetToolTip(this.userListView, global::MafiaRatings.Properties.Resources.String1);
            this.userListView.UseCompatibleStateImageBehavior = false;
            this.userListView.View = System.Windows.Forms.View.List;
            this.userListView.ItemSelectionChanged += new System.Windows.Forms.ListViewItemSelectionChangedEventHandler(this.userListView_ItemSelectionChanged);
            this.userListView.SelectedIndexChanged += new System.EventHandler(this.userListView_SelectedIndexChanged);
            this.userListView.MouseClick += new System.Windows.Forms.MouseEventHandler(this.userListView_MouseClick);
            // 
            // userNameTextBox
            // 
            resources.ApplyResources(this.userNameTextBox, "userNameTextBox");
            this.userNameTextBox.Name = "userNameTextBox";
            this.toolTip.SetToolTip(this.userNameTextBox, global::MafiaRatings.Properties.Resources.String1);
            this.userNameTextBox.TextChanged += new System.EventHandler(this.userNameTextBox_TextChanged);
            // 
            // otherClubCheckBox
            // 
            resources.ApplyResources(this.otherClubCheckBox, "otherClubCheckBox");
            this.otherClubCheckBox.ImageKey = global::MafiaRatings.Properties.Resources.String1;
            this.otherClubCheckBox.Name = "otherClubCheckBox";
            this.toolTip.SetToolTip(this.otherClubCheckBox, global::MafiaRatings.Properties.Resources.String1);
            this.otherClubCheckBox.UseVisualStyleBackColor = true;
            this.otherClubCheckBox.CheckedChanged += new System.EventHandler(this.otherClubCheckBox_CheckedChanged);
            // 
            // SelectUserControl
            // 
            resources.ApplyResources(this, "$this");
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.Controls.Add(this.otherClubCheckBox);
            this.Controls.Add(this.userListView);
            this.Controls.Add(this.userNameTextBox);
            this.Controls.Add(label1);
            this.Name = "SelectUserControl";
            this.toolTip.SetToolTip(this, global::MafiaRatings.Properties.Resources.String1);
            this.ResumeLayout(false);
            this.PerformLayout();

        }

        #endregion

        private System.Windows.Forms.ListView userListView;
        private System.Windows.Forms.TextBox userNameTextBox;
        private System.Windows.Forms.ToolTip toolTip;
        private System.Windows.Forms.CheckBox otherClubCheckBox;
    }
}
