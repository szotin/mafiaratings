using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using MafiaRatings.GameObjects;

namespace MafiaRatings
{
    public partial class ClubsDialog : Form
    {
        public ClubsDialog()
        {
            InitializeComponent();

            int selected = 0;
            foreach (Club club in Database.Gate.Clubs)
            {
                if (club.Id == Database.Gate.Club.Id)
                {
                    selected = clubsListBox.Items.Add(club);
                }
                else
                {
                    clubsListBox.Items.Add(club);
                }
            }
            clubsListBox.SelectedIndex = selected;
        }

        private void clubsListBox_MouseDoubleClick(object sender, MouseEventArgs e)
        {
            DialogResult = System.Windows.Forms.DialogResult.OK;
            Close();
        }

        public Club Club
        {
            get
            {
                Club club = null;
                if (clubsListBox.SelectedIndex >= 0)
                {
                    club = clubsListBox.Items[clubsListBox.SelectedIndex] as Club;
                }
                if (club == null)
                {
                    return Database.Gate.Club;
                }
                return club;
            }
        }
    }
}
