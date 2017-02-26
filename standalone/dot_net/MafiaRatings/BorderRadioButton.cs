using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Windows.Forms;

namespace MafiaRatings
{
    public partial class BorderRadioButton : UserControl
    {
        public BorderRadioButton()
        {
            InitializeComponent();
        }

        public string String
        {
            get { return radioButton.Text; }
            set { radioButton.Text = value; }
        }

        public bool Checked
        {
            get { return radioButton.Checked; }
            set { radioButton.Checked = value; }
        }

        public bool Grayed
        {
            get { return !radioButton.Enabled; }
            set { radioButton.Enabled = !value; }
        }

        private void BorderRadioButton_EnabledChanged(object sender, EventArgs e)
        {
            radioButton.Visible = Enabled;
        }

        public event EventHandler CheckedChanged
        {
            add { radioButton.CheckedChanged += value; }
            remove { radioButton.CheckedChanged -= value; }
        }

        public bool IsMyEvent(object sender)
        {
            return radioButton == sender;
        }
    }
}
