using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using MafiaRatings.GameObjects;

namespace MafiaRatings
{
    public partial class SelectUserControl : UserControl
    {
        private UserSelected mOnUserSelected;
        private SelectionChanged mOnSelectionChanged;
        private Party mParty;

        public delegate void UserSelected(UserRef user);
        public delegate void SelectionChanged(bool selected);

        public SelectUserControl()
        {
            InitializeComponent();
            readUserList();
        }

        private void readUserList()
        {
            userListView.Items.Clear();
            if (mParty == null)
            {
                return;
            }

            List<UserRef> users = null;
            ProgressDialog.Execute(delegate()
            {
                users = Database.Users.Query(userNameTextBox.Text, mParty.Id, otherClubCheckBox.Checked);
            });
            foreach (UserRef user in users)
            {
                ListViewItem item = new ListViewItem(user.Name);
                item.Tag = user;
                userListView.Items.Add(item);
            }

            userListView.SelectedItems.Clear();
            if (users.Count == 1)
            {
                userListView.SelectedIndices.Add(0);
            }

            if (mOnSelectionChanged != null)
            {
                mOnSelectionChanged(userListView.SelectedItems.Count == 1);
            }
        }

        private void userNameTextBox_TextChanged(object sender, EventArgs e)
        {
            readUserList();
        }

        private void userListView_ItemSelectionChanged(object sender, ListViewItemSelectionChangedEventArgs e)
        {
            if (mOnSelectionChanged != null)
            {
                mOnSelectionChanged(userListView.SelectedItems.Count == 1);
            }
        }

        private void userListView_SelectedIndexChanged(object sender, EventArgs e)
        {
            if (mOnSelectionChanged != null)
            {
                if (userListView.SelectedItems.Count == 1)
                {
                    User user = null;
                    UserRef userRef = userListView.SelectedItems[0].Tag as UserRef;
                    if (userRef != null)
                    {
                        user = userRef.User;
                    }
                    if (user != null && user.NicksCount > 0)
                    {
                        StringBuilder tip = new StringBuilder();
                        foreach (UserNick nick in user.Nicks)
                        {
                            tip.Append(nick.Nick);
                            tip.Append('\n');
                        }
                        toolTip.SetToolTip(userListView, tip.ToString());
                    }
                    else
                    {
                        toolTip.SetToolTip(userListView, "");
                    }
                    mOnSelectionChanged(true);
                }
                else
                {
                    mOnSelectionChanged(false);
                }
            }
        }

        public UserSelected OnUserSelected
        {
            get { return mOnUserSelected; }
            set { mOnUserSelected = value; }
        }

        public SelectionChanged OnSelectionChanged
        {
            get { return mOnSelectionChanged; }
            set
            {
                mOnSelectionChanged = value;
                if (mOnSelectionChanged != null)
                {
                    mOnSelectionChanged(userListView.SelectedItems.Count == 1);
                }
            }
        }

        private void userListView_MouseClick(object sender, MouseEventArgs e)
        {
            if (userListView.SelectedItems.Count == 1 && mOnUserSelected != null)
            {
                mOnUserSelected((UserRef)userListView.SelectedItems[0].Tag);
            }
        }

        public UserRef SelectedUser
        {
            get
            {
                if (userListView.SelectedItems.Count == 1)
                {
                    return (UserRef)userListView.SelectedItems[0].Tag;
                }
                return null;
            }
        }

        public Party Party
        {
            get { return mParty; }
            set
            {
                if (value != mParty)
                {
                    mParty = value;
                    userNameTextBox_TextChanged(this, null);
                }
            }
        }

        private void otherClubCheckBox_CheckedChanged(object sender, EventArgs e)
        {
            if (otherClubCheckBox.Checked)
            {
                if (!Database.Gate.Connected)
                {
                    MessageBox.Show(Properties.Resources.ConnectToRegOtherClub, Properties.Resources.Error, MessageBoxButtons.OK, MessageBoxIcon.Error);
                    otherClubCheckBox.Checked = false;
                    return;
                }
            }
            readUserList();
        }
    }
}
