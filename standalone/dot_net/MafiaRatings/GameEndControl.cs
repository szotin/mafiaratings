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
    public partial class GameEndControl : GameControl
    {
        private struct PlayerControls
        {
            public BorderLabel number;
            public BorderRadioButton name;
            public BorderLabel rating;
            public BorderLabel sheriff;
            public BorderLabel don;
            public BorderLabel arrangement;
            public BorderLabel killed;
            public BorderLabel warnings;
            public BorderLabel role;
        }

        private PlayerControls[] mPlayerControls;

        private void InitPlayerControls()
        {
            mPlayerControls = new PlayerControls[10];

            mPlayerControls[0].number = numberLabel1;
            mPlayerControls[0].name = playerRadioButton1;
            mPlayerControls[0].rating = ratingLabel1;
            mPlayerControls[0].sheriff = sheriffLabel1;
            mPlayerControls[0].don = donLabel1;
            mPlayerControls[0].arrangement = arrangementLabel1;
            mPlayerControls[0].killed = killedLabel1;
            mPlayerControls[0].warnings = warinigsLabel1;
            mPlayerControls[0].role = roleLabel1;

            mPlayerControls[1].number = numberLabel2;
            mPlayerControls[1].name = playerRadioButton2;
            mPlayerControls[1].rating = ratingLabel2;
            mPlayerControls[1].sheriff = sheriffLabel2;
            mPlayerControls[1].don = donLabel2;
            mPlayerControls[1].arrangement = arrangementLabel2;
            mPlayerControls[1].killed = killedLabel2;
            mPlayerControls[1].warnings = warinigsLabel2;
            mPlayerControls[1].role = roleLabel2;

            mPlayerControls[2].number = numberLabel3;
            mPlayerControls[2].name = playerRadioButton3;
            mPlayerControls[2].rating = ratingLabel3;
            mPlayerControls[2].sheriff = sheriffLabel3;
            mPlayerControls[2].don = donLabel3;
            mPlayerControls[2].arrangement = arrangementLabel3;
            mPlayerControls[2].killed = killedLabel3;
            mPlayerControls[2].warnings = warinigsLabel3;
            mPlayerControls[2].role = roleLabel3;

            mPlayerControls[3].number = numberLabel4;
            mPlayerControls[3].name = playerRadioButton4;
            mPlayerControls[3].rating = ratingLabel4;
            mPlayerControls[3].sheriff = sheriffLabel4;
            mPlayerControls[3].don = donLabel4;
            mPlayerControls[3].arrangement = arrangementLabel4;
            mPlayerControls[3].killed = killedLabel4;
            mPlayerControls[3].warnings = warinigsLabel4;
            mPlayerControls[3].role = roleLabel4;

            mPlayerControls[4].number = numberLabel5;
            mPlayerControls[4].name = playerRadioButton5;
            mPlayerControls[4].rating = ratingLabel5;
            mPlayerControls[4].sheriff = sheriffLabel5;
            mPlayerControls[4].don = donLabel5;
            mPlayerControls[4].arrangement = arrangementLabel5;
            mPlayerControls[4].killed = killedLabel5;
            mPlayerControls[4].warnings = warinigsLabel5;
            mPlayerControls[4].role = roleLabel5;

            mPlayerControls[5].number = numberLabel6;
            mPlayerControls[5].name = playerRadioButton6;
            mPlayerControls[5].rating = ratingLabel6;
            mPlayerControls[5].sheriff = sheriffLabel6;
            mPlayerControls[5].don = donLabel6;
            mPlayerControls[5].arrangement = arrangementLabel6;
            mPlayerControls[5].killed = killedLabel6;
            mPlayerControls[5].warnings = warinigsLabel6;
            mPlayerControls[5].role = roleLabel6;

            mPlayerControls[6].number = numberLabel7;
            mPlayerControls[6].name = playerRadioButton7;
            mPlayerControls[6].rating = ratingLabel7;
            mPlayerControls[6].sheriff = sheriffLabel7;
            mPlayerControls[6].don = donLabel7;
            mPlayerControls[6].arrangement = arrangementLabel7;
            mPlayerControls[6].killed = killedLabel7;
            mPlayerControls[6].warnings = warinigsLabel7;
            mPlayerControls[6].role = roleLabel7;

            mPlayerControls[7].number = numberLabel8;
            mPlayerControls[7].name = playerRadioButton8;
            mPlayerControls[7].rating = ratingLabel8;
            mPlayerControls[7].sheriff = sheriffLabel8;
            mPlayerControls[7].don = donLabel8;
            mPlayerControls[7].arrangement = arrangementLabel8;
            mPlayerControls[7].killed = killedLabel8;
            mPlayerControls[7].warnings = warinigsLabel8;
            mPlayerControls[7].role = roleLabel8;

            mPlayerControls[8].number = numberLabel9;
            mPlayerControls[8].name = playerRadioButton9;
            mPlayerControls[8].rating = ratingLabel9;
            mPlayerControls[8].sheriff = sheriffLabel9;
            mPlayerControls[8].don = donLabel9;
            mPlayerControls[8].arrangement = arrangementLabel9;
            mPlayerControls[8].killed = killedLabel9;
            mPlayerControls[8].warnings = warinigsLabel9;
            mPlayerControls[8].role = roleLabel9;

            mPlayerControls[9].number = numberLabel10;
            mPlayerControls[9].name = playerRadioButton10;
            mPlayerControls[9].rating = ratingLabel10;
            mPlayerControls[9].sheriff = sheriffLabel10;
            mPlayerControls[9].don = donLabel10;
            mPlayerControls[9].arrangement = arrangementLabel10;
            mPlayerControls[9].killed = killedLabel10;
            mPlayerControls[9].warnings = warinigsLabel10;
            mPlayerControls[9].role = roleLabel10;
        }

        public GameEndControl()
        {
            InitializeComponent();
            InitPlayerControls();

            playerLabel.String = Properties.Resources.Player;
            ratingLabel.String = Properties.Resources.RatingPoints;
            sheriffLabel.String = Properties.Resources.SheriffIsChecking;
            donLabel.String = Properties.Resources.DonIsChecking;
            arrangeLabel.String = Properties.Resources.MafiaArrangement;
            killedLabel.String = Properties.Resources.Killed;
            warningsLabel.String = Properties.Resources.PlayerWarnings;
            roleLabel.String = Properties.Resources.Role;
            noPlayerRadioButton.String = Properties.Resources.NoBestPlayer;
        }

        public static bool IsMyGameState(GameState state)
        {
            switch (state)
            {
                case GameState.MafiaWon:
                case GameState.CivilWon:
                case GameState.Terminated:
                    return true;
            }
            return false;
        }

        public override bool IsActive
        {
            get { return IsMyGameState(Database.Game.State); }
        }

        protected override void OnReload()
        {
            Game game = Database.Game;
            switch (game.State)
            {
                case GameState.MafiaWon:
                    titleLabel.Text = Properties.Resources.MafiaWins;
                    break;
                case GameState.CivilWon:
                    titleLabel.Text = Properties.Resources.CiviliansWin;
                    break;
                case GameState.Terminated:
                    titleLabel.Text = Properties.Resources.GameTerminated;
                    break;
            }
            
            for (int i = 0; i < 10; ++i)
            {
                GamePlayer player = game.Players[i];
                PlayerControls controls = mPlayerControls[i];

                controls.name.String = player.Name;
                controls.name.Checked = (i == Database.Game.BestPlayer);
                if (player.IsDummy)
                {
                    controls.name.Grayed = true;
                }
                controls.rating.String = game.GetRating(i).ToString();
                controls.sheriff.String = player.SheriffCheckText;
                controls.don.String = player.DonCheckText;
                controls.arrangement.String = player.ArrangedText;
                controls.killed.String = player.KilledText;
                controls.warnings.String = player.WarningsText;
                controls.role.String = player.RoleText;
            }
            noPlayerRadioButton.Checked = (Database.Game.BestPlayer < 0 || Database.Game.BestPlayer >= 10);
        }

        private void playerRadioButton_CheckedChanged(object sender, EventArgs e)
        {
            if (Initializing)
            {
                return;
            }

            if (noPlayerRadioButton.IsMyEvent(sender))
            {
                Database.Game.BestPlayer = -1;
            }
            else
            {
                for (int i = 0; i < 10; ++i)
                {
                    PlayerControls controls = mPlayerControls[i];
                    if (controls.name.IsMyEvent(sender))
                    {
                        Database.Game.BestPlayer = i;
                        break;
                    }
                }
            }

            Initializing = true;
            for (int i = 0; i < 10; ++i)
            {
                PlayerControls controls = mPlayerControls[i];
                controls.rating.String = Database.Game.GetRating(i).ToString();
                controls.name.Checked = (i == Database.Game.BestPlayer);
            }
            noPlayerRadioButton.Checked = (Database.Game.BestPlayer < 0 || Database.Game.BestPlayer >= 10);
            Initializing = false;
        }
    }
}
